<?php

namespace App\Domain\Pickup;

use App\Domain\Pickup\Handlers\RegisteredTrackingNumberPickupHandler;
use App\Domain\Pickup\Handlers\PreIdWithWaybillPickupHandler;
use App\Domain\Pickup\Handlers\PreIdPickupHandler;
use App\Domain\Pickup\Handlers\MerchantPickupShipmentPickupHandler;
use App\Domain\Pickup\Handlers\MerchantPickupShipmentWaybillPickupHandler;
use App\Models\Shipment;
use App\Models\Driver;
use App\Models\DriverBonus;
use App\Models\DriverBonusesTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use App\Enums\DriverBonusesTransactionActionsEnum;

/**
 * Factory class for handling shipment pickup scenarios.
 *
 * This factory creates appropriate handlers for different pickup scenarios and
 * manages driver bonuses for successful pickups.
 *
 * PICKUP BONUSES:
 * - Bonuses are automatically added when a driver successfully picks up a shipment
 * - Bonus records are stored in driver_bonuses_transactions table
 * - Bonus amount is determined by DriverBonus model (pickup_bonus field)
 * - Reference format: "BON-PU-{tracking_no}" or "BON-PU-{pre_id}"
 * - Description format: "Pickup Bonus - Shipment #{identifier} ({state_name})"
 *
 * VIEWING BONUSES:
 * - Total bonus: Calculated by DriverBonusesTransaction::driverBonuses($driverId)
 * - Individual records: Queried from driver_bonuses_transactions where reference LIKE 'BON-PU-%'
 * - Displayed in driver account page at: /drivers/accounts/{driver_id}
 * - Use getPickupBonuses() or getPickupBonusStats() methods to retrieve detailed information
 */
class ShipmentPickupFactory
{
    /**
     * Create the appropriate ShipmentPickupHandler based on input.
     *
     * @param array $input
     *      Keys: 'tracking_no', 'pre_id', 'waybill_tracking_no', 'proof'
     * @return ShipmentPickupHandlerInterface
     */
    public static function make(array $input): ShipmentPickupHandlerInterface
    {
        $hasTrackingNo = !empty($input['tracking_no']);
        $hasPreId = !empty($input['pre_id']);
        $hasWaybill = !empty($input['waybill_tracking_no']);
        $hasProof = !empty($input['pickup_proof']);
        $hasPickupTaskId = !empty($input['pickup_task_id']);

        // 1️⃣ Registered shipment OR unregistered waybill w/ tracking number + proof
        // Tracking_no + proof (handler will check if shipment exists or if it's a merchant waybill)
        // 📦 Shipment Identified with Tracking Number
        if ($hasTrackingNo && $hasProof && !$hasPreId && !$hasWaybill) {
            // Always use RegisteredTrackingNumberPickupHandler - it handles both registered shipments
            // and unregistered merchant waybills internally
            return new RegisteredTrackingNumberPickupHandler();
        }

        // 2️⃣ PRE-ID only
        // PRE-ID only + proof
        // 📦 Shipment Identified with Pre-id
        if ($hasPreId && !$hasWaybill && $hasProof) {
            return new PreIdPickupHandler();
        }

        // 3️⃣ PRE-ID + driver's waybill (most specific - check first)
        // PRE-ID + merchant's/driver's waybill + proof
        // 📦 Shipment Identified with Pre-id
        if ($hasPreId && $hasWaybill && $hasProof) {
            return new PreIdWithWaybillPickupHandler();
        }

        // 4️⃣ Unassigned shipment with merchant/driver waybill (waybill_tracking_no + proof); pickup_task_id required
        if (!$hasPreId && !$hasTrackingNo && $hasWaybill && $hasPickupTaskId && $hasProof) {
            // Unassigned shipment with merchant/driver waybill - use the unassigned shipment waybill handler
            return new MerchantPickupShipmentWaybillPickupHandler();
        }

        // 5️⃣ Unassigned shipment with proof (proof or proof); pickup_task_id required
        if (!$hasTrackingNo && !$hasPreId && !$hasWaybill && $hasPickupTaskId && ($hasProof || $hasProof)) {
            return new MerchantPickupShipmentPickupHandler();
        }

        throw new InvalidArgumentException('Cannot determine pickup handler from input.');
    }

    /**
     * Add pickup bonus to driver for a successfully picked shipment.
     * This is called after the pickup is marked as successful (status = PICKED).
     *
     * @param Shipment $shipment The shipment that was picked
     * @param int $driverId The driver who picked the shipment
     * @return bool True if bonus was created, false otherwise
     */

    public static function addPickupBonus(Shipment $shipment, int $driverId): bool
    {
        try {
            Log::info("Pickup bonus: Starting bonus calculation", [
                'shipment_id' => $shipment->id,
                'driver_id' => $driverId,
                'tracking_no' => $shipment->tracking_no,
                'pre_id' => $shipment->pre_id,
            ]);

            // Get driver model
            $driver = User::find($driverId);
            if (!$driver) {
                Log::warning("Pickup bonus: Driver not found", ['driver_id' => $driverId]);
                return false;
            }

            // Get facility info
            $facilityType = $driver->owner_type;
            $facilityId = $driver->owner_id;

            if (!$facilityType || !$facilityId) {
                Log::warning("Pickup bonus: Driver has no facility", [
                    'driver_id' => $driverId,
                    'owner_type' => $facilityType,
                    'owner_id' => $facilityId,
                ]);
                return false;
            }

            // Get state_id from shipment
            $stateId = $shipment->state_id
                ?? optional($shipment->consignee)->state_id
                ?? optional($shipment->deliveryAddress)->state_id
                ?? null;

            Log::info("Pickup bonus: Query parameters", [
                'driver_id' => $driverId,
                'state_id' => $stateId,
                'facility_type' => $facilityType,
                'facility_id' => $facilityId,
            ]);

            // Get pickup bonus rate from DriverBonus model
            // Note: driver_id in driver_bonuses table stores user_id (not Driver model id)
            $bonusRow = DriverBonus::query()
                ->where('driver_id', $driverId) // Use user_id directly
                ->when(!empty($stateId), fn($q) => $q->where('state_id', $stateId))
                ->where(function ($q) use ($facilityType, $facilityId) {
                    $q->where(function ($q1) use ($facilityType, $facilityId) {
                        $q1->where('owner_type', $facilityType)->where('owner_id', $facilityId);
                    })->orWhere(function ($q2) {
                        $q2->whereNull('owner_type')->whereNull('owner_id');
                    });
                })
                ->orderByRaw(
                    'CASE ' .
                        'WHEN owner_type = ? AND owner_id = ? THEN 0 ' .
                        'WHEN owner_type IS NULL AND owner_id IS NULL THEN 1 ' .
                        'ELSE 2 END',
                    [$facilityType, $facilityId]
                )
                ->first();

            if (!$bonusRow) {
                Log::info("Pickup bonus: No bonus configuration found", [
                    'driver_id' => $driverId,
                    'state_id' => $stateId,
                    'facility_type' => $facilityType,
                    'facility_id' => $facilityId,
                ]);
                return false;
            }

            // Get pickup_bonus as bonus_rate for pickup bonuses
            $bonusRate = $shipment->is_return 
                ? (float) ($bonusRow->return_pickup_bonus ?? 0)
                : (float) ($bonusRow->pickup_bonus ?? 0);

            Log::info("Pickup bonus: Bonus rate found", [
                'bonus_row_id' => $bonusRow->id,
                'pickup_bonus' => $bonusRate,
            ]);

            if ($bonusRate <= 0) {
                Log::info("Pickup bonus: Bonus rate is zero or negative", [
                    'pickup_bonus' => $bonusRate,
                ]);
                return false;
            }

            // Get shipment identifiers
            $trackingNo = $shipment->tracking_no ?? null;
            $preId = $shipment->pre_id ?? null;
            $shipmentIdentifier = $trackingNo ?? $preId ?? "ID-{$shipment->id}";

            // Get driver_runsheet_id from shipment's runsheet_shipment if available
            $driverRunsheetId = null;
            $runsheetShipment = $shipment->runsheet_shipment;
            if ($runsheetShipment) {
                $driverRunsheetId = $runsheetShipment->runsheet_id;
            }

            // Create reference
            $ref = 'BON-PU-' . $shipmentIdentifier;

            // Create driver bonus transaction description
            $stateName = $stateId ? optional(\App\Models\State::find($stateId))->name : null;
            $description = "Pickup Bonus - Shipment #{$shipmentIdentifier}";
            if ($stateName) {
                $description .= " ({$stateName})";
            } elseif ($stateId) {
                $description .= " (State #{$stateId})";
            }
            

            // Use updateOrCreate to handle duplicates gracefully
            // Unique key: driver_id + shipment_id + action
            $action = $shipment->is_return 
                ? DriverBonusesTransactionActionsEnum::RETURN_PICKUP
                : DriverBonusesTransactionActionsEnum::PICKUP;
                
            $bonusTransaction = DriverBonusesTransaction::updateOrCreate(
                [
                    // Unique key columns
                    'driver_id' => $driverId,
                    'shipment_id' => $shipment->id,
                    'action' => $action,
                ],
                [
                    // Values to set/update
                    'shipment_tracking_no' => $trackingNo,
                    'pre_id' => $preId,
                    'state_id' => $stateId,
                    'driver_runsheet_id' => $driverRunsheetId,
                    'bonus_amount' => $bonusRate,
                    'bonus_rate' => $bonusRate,
                    'reference' => $ref,
                    'description' => $description,
                    'active' => true,
                    'status' => 'pending',
                    'created_by' => $driverId,
                ]
            );

            $wasRecentlyCreated = $bonusTransaction->wasRecentlyCreated;

            Log::info("Pickup bonus: Driver bonus transaction " . ($wasRecentlyCreated ? 'created' : 'updated'), [
                'bonus_transaction_id' => $bonusTransaction->id,
                'was_created' => $wasRecentlyCreated,
                'driver_id' => $driverId,
                'shipment_id' => $shipment->id,
                'shipment_tracking_no' => $trackingNo,
                'pre_id' => $preId,
                'state_id' => $stateId,
                'driver_runsheet_id' => $driverRunsheetId,
                'bonus_amount' => $bonusRate,
                'bonus_rate' => $bonusRate,
                'reference' => $ref,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error("Pickup bonus: Failed to create driver bonus transaction", [
                'driver_id' => $driverId,
                'shipment_id' => $shipment->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }
    /**
     * Activate pickup bonus for a successfully delivered shipment.
     * This is called when COD collection is accepted to activate the pickup bonus.
     *
     * @param int $shipmentId The shipment ID
     * @return bool True if bonus was activated, false otherwise
     */
    public static function activatePickupBonus(int $shipmentId): bool
    {
        try {
            Log::info("Activating pickup bonus", [
                'shipment_id' => $shipmentId,
            ]);

            // Find the pickup bonus transaction for this shipment
            $bonusTransaction = DriverBonusesTransaction::query()
                ->where('shipment_id', $shipmentId)
                ->where('action', DriverBonusesTransactionActionsEnum::PICKUP)
                ->where('status', 'pending')
                ->first();

            if (!$bonusTransaction) {
                Log::warning("Activating pickup bonus: No inactive pickup bonus found", [
                    'shipment_id' => $shipmentId,
                ]);
                return false;
            }

            // Update active status to true
            $bonusTransaction->update(['status' => 'delivered']);

            Log::info("Pickup bonus activated successfully", [
                'bonus_transaction_id' => $bonusTransaction->id,
                'driver_id' => $bonusTransaction->driver_id,
                'shipment_id' => $shipmentId,
                'bonus_amount' => $bonusTransaction->bonus_amount,
                'reference' => $bonusTransaction->reference,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error("Failed to activate pickup bonus", [
                'shipment_id' => $shipmentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    // /**
    //  * Get all pickup bonus transactions for a driver.
    //  * This method retrieves all DriverBonusesTransaction records that were created
    //  * from pickup scenarios (identified by reference starting with 'BON-PU-').
    //  *
    //  * @param int $driverId The driver's user ID
    //  * @param array $options Optional filters: 'from', 'to' (date range), 'active' (bool)
    //  * @return \Illuminate\Database\Eloquent\Collection
    //  */
    // public static function getPickupBonuses(int $driverId, array $options = []): \Illuminate\Database\Eloquent\Collection
    // {
    //     $query = DriverBonusesTransaction::query()
    //         ->where('driver_id', $driverId)
    //         ->where('reference', 'like', 'BON-PU-%')
    //         ->with(['shipment', 'state']);

    //     // Filter by active status if provided
    //     if (isset($options['active'])) {
    //         $query->where('active', (bool) $options['active']);
    //     }

    //     // Filter by date range if provided
    //     if (!empty($options['from'])) {
    //         $query->where('created_at', '>=', $options['from']);
    //     }
    //     if (!empty($options['to'])) {
    //         $query->where('created_at', '<=', $options['to']);
    //     }

    //     return $query->orderBy('created_at', 'desc')->get();
    // }

    // /**
    //  * Get pickup bonus statistics for a driver.
    //  *
    //  * @param int $driverId The driver's user ID
    //  * @param array $options Optional filters: 'from', 'to' (date range)
    //  * @return array Statistics including total, count, and breakdown
    //  */
    // public static function getPickupBonusStats(int $driverId, array $options = []): array
    // {
    //     $query = DriverBonusesTransaction::query()
    //         ->where('driver_id', $driverId)
    //         ->where('reference', 'like', 'BON-PU-%')
    //         ->where('active', true);

    //     // Filter by date range if provided
    //     if (!empty($options['from'])) {
    //         $query->where('created_at', '>=', $options['from']);
    //     }
    //     if (!empty($options['to'])) {
    //         $query->where('created_at', '<=', $options['to']);
    //     }

    //     $bonuses = $query->get();

    //     $total = (float) $bonuses->sum('bonus_amount');
    //     $count = $bonuses->count();

    //     // Breakdown by state
    //     $byState = $bonuses->groupBy('state_id')->map(function ($group, $stateId) {
    //         return [
    //             'state_id' => $stateId,
    //             'state_name' => optional(\App\Models\State::find($stateId))->name,
    //             'count' => $group->count(),
    //             'total' => (float) $group->sum('bonus_amount'),
    //         ];
    //     })->values();

    //     return [
    //         'total_bonus' => $total,
    //         'total_count' => $count,
    //         'average_bonus' => $count > 0 ? $total / $count : 0,
    //         'by_state' => $byState,
    //         'records' => $bonuses->map(function ($bonus) {
    //             return [
    //                 'id' => $bonus->id,
    //                 'shipment_id' => $bonus->shipment_id,
    //                 'tracking_no' => $bonus->shipment_tracking_no,
    //                 'pre_id' => $bonus->pre_id,
    //                 'state_id' => $bonus->state_id,
    //                 'state_name' => optional($bonus->state)->name,
    //                 'bonus_amount' => (float) $bonus->bonus_amount,
    //                 'reference' => $bonus->reference,
    //                 'description' => $bonus->description,
    //                 'created_at' => $bonus->created_at,
    //                 'active' => $bonus->active,
    //             ];
    //         }),
    //     ];
    // }
}
