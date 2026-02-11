<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;


use App\Http\Resources\SorterResource;
use App\Models\MerchantPickupShipment;
use App\Models\MerchantPickupTask;
use App\Models\CrmTask;
use App\Models\DeliveryException;
use App\Models\DriverShipmentAssignment;
use App\Models\DriverRunsheetShipment;
use App\Models\Shipment;
use App\Models\ShipmentHistory;
use App\Models\State;
use App\Models\StockOutTask;
use App\Models\StockOutTaskShipment;
use App\Models\TransferDestination;
use App\Models\TransferShipment;
use App\Models\TransferTask;
use App\Models\TransferTaskShipment;
use App\Models\PickuptaskTransaction;
use App\Models\Truck;
use App\Models\Zone;
use App\Models\ZoneShipment;
use App\Services\FeeAllocator;
use App\Services\OFDService;
use App\Services\ShipmentValidationService;
use App\Services\ParcelShelfService;
use App\Services\SorterService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Enums\ShipmentStatusEnum;
use App\Domain\Pickup\ShipmentPickupFactory;
use Illuminate\Support\Facades\Log;

/**
 * Controller handling complex shipment sorting operations
 * Purpose: Sorting is the process of diffrentiating the parcels on the basis of their addresses.
 *
 * Manages shipment lifecycle stages including:
 * - Inbound/outbound sorting
 * - Transfer task loading/unloading
 * - Warehouse organization
 * - Delivery exception handling
 * - Pickup completion workflows
 *
 * Features:
 * - Zone-based routing logic
 * - Transfer task management
 * - Delivery exception resolution
 * - Automated status history tracking
 * - Financial account integration
 * - Multi-facility transfer support
 */
class SorterController extends Controller
{
    public function resolveZoneForShipment(Shipment $shipment): ?Zone
    {
        $isReturn = (bool) $shipment->is_return;
        $useMerchantDestination = $isReturn && $this->shouldResolveReturnToMerchant($shipment);

        if ($useMerchantDestination) {
            $shipment->loadMissing([
                'merchant.merchant.place',
                'merchant.merchant.state',
                'merchant.merchant.governorate',
                'deliveryAddress',
            ]);
        } elseif ($isReturn) {
            $shipment->loadMissing([
                'pickupAddress.place',
                'pickupAddress.state',
                'pickupAddress.governorate',
                'consignee.place',
                'consignee.state',
                'consignee.governorate',
            ]);
        } else {
            $shipment->loadMissing([
                'deliveryAddress.place',
                'deliveryAddress.state',
                'deliveryAddress.governorate',
                'consignee.place',
                'consignee.state',
                'consignee.governorate',
            ]);
        }

        $consignee = $shipment->consignee;
        $merchantProfile = optional($shipment->merchant)->merchant;
        if (!$merchantProfile && $shipment->merchant_id) {
            $merchantProfile = \App\Models\Merchant::where('user_id', $shipment->merchant_id)->first()
                ?? \App\Models\Merchant::find($shipment->merchant_id);
        }

        $shipmentPlaceId = $shipment->place_id;
        $shipmentStateId = $shipment->state_id;

        if ($useMerchantDestination) {
            $merchantPlaceId = optional($merchantProfile)->place_id ?? optional(optional($merchantProfile)->place)->id;
            $merchantStateId = optional($merchantProfile)->state_id ?? optional(optional($merchantProfile)->state)->id;
            $merchantGovernorateId = optional($merchantProfile)->governorate_id ?? optional(optional($merchantProfile)->governorate)->id;
            $deliveryPlaceId = optional($shipment->deliveryAddress)->place_id;
            $deliveryStateId = optional($shipment->deliveryAddress)->state_id;
            $deliveryGovernorateId = optional($shipment->deliveryAddress)->governorate_id;

            $placeId = $merchantPlaceId ?? $deliveryPlaceId ?? $shipmentPlaceId;
            $stateId = $merchantStateId ?? $deliveryStateId ?? $shipmentStateId;
            $governorateId = $merchantGovernorateId ?? $deliveryGovernorateId ?? $shipment->governorate_id;
        } elseif ($isReturn) {
            $consigneePlaceId = optional($consignee)->place_id ?? optional(optional($consignee)->place)->id;
            $consigneeStateId = optional($consignee)->state_id ?? optional(optional($consignee)->state)->id;
            $consigneeGovernorateId = optional($consignee)->governorate_id ?? optional(optional($consignee)->governorate)->id;
            $pickupGovernorateId = optional($shipment->pickupAddress)->governorate_id;

            $placeId = $shipmentPlaceId ?? $consigneePlaceId;
            $stateId = $shipmentStateId ?? $consigneeStateId;
            $governorateId = $pickupGovernorateId ?? $consigneeGovernorateId ?? $shipment->governorate_id;
        } else {
            $addressPlaceId = optional($shipment->deliveryAddress)->place_id;
            $addressStateId = optional($shipment->deliveryAddress)->state_id;
            $addressGovernorateId = optional($shipment->deliveryAddress)->governorate_id;

            $consigneePlaceId = optional($consignee)->place_id ?? optional(optional($consignee)->place)->id;
            $consigneeStateId = optional($consignee)->state_id ?? optional(optional($consignee)->state)->id;
            $consigneeGovernorateId = optional($consignee)->governorate_id ?? optional(optional($consignee)->governorate)->id;

            $placeId = $shipmentPlaceId ?? $addressPlaceId ?? $consigneePlaceId;
            $stateId = $shipmentStateId ?? $addressStateId ?? $consigneeStateId;
            $governorateId = $addressGovernorateId ?? $consigneeGovernorateId ?? $shipment->governorate_id;
        }

        // 2) Search by place mapping if exists
        if ($placeId) {
            $zone = Zone::whereHas('assignedPlaces', fn($q) => $q->where('places.id', $placeId))
                ->first();
            if ($zone)
                return $zone;
        }

        // 3) Search by state mapping if exists
        if ($stateId) {
            $zone = Zone::query()
                ->join('state_zones as sz', 'sz.zone_id', '=', 'zones.id')
                ->where('sz.state_id', $stateId)
                ->select('zones.*')
                ->first();
            if ($zone) {
                return $zone;
            }

            $zone = Zone::whereHas('selectedStates', fn($q) => $q->where('states.id', $stateId))
                ->first();
            if ($zone)
                return $zone;
        }

        if ($governorateId) {
            $zone = Zone::query()
                ->join('state_zones as sz', 'sz.zone_id', '=', 'zones.id')
                ->join('states as st', 'st.id', '=', 'sz.state_id')
                ->where('st.governorate_id', $governorateId)
                ->select('zones.*')
                ->first();
            if ($zone) {
                return $zone;
            }
        }

        if ($useMerchantDestination) {
            $lng = optional($merchantProfile)->lng ?? optional($shipment->deliveryAddress)->longitude;
            $lat = optional($merchantProfile)->lat ?? optional($shipment->deliveryAddress)->latitude;

            if (($lng === null || $lat === null) && optional(optional($merchantProfile)->place)->lng && optional(optional($merchantProfile)->place)->lat) {
                $lng = optional($merchantProfile)->place->lng;
                $lat = optional($merchantProfile)->place->lat;
            }

            if (($lng === null || $lat === null) && optional(optional($merchantProfile)->governorate)->lng && optional(optional($merchantProfile)->governorate)->lat) {
                $lng = optional($merchantProfile)->governorate->lng;
                $lat = optional($merchantProfile)->governorate->lat;
            }
        } else {
            $address = $isReturn ? $shipment->pickupAddress : $shipment->deliveryAddress;
            $lng = $shipment->longitude ?? optional($address)->longitude ?? optional($consignee)->longitude;
            $lat = $shipment->latitude ?? optional($address)->latitude ?? optional($consignee)->latitude;

            if (($lng === null || $lat === null) && optional(optional($consignee)->place)->lng && optional(optional($consignee)->place)->lat) {
                $lng = optional($consignee)->place->lng;
                $lat = optional($consignee)->place->lat;
            }

            if (($lng === null || $lat === null) && optional(optional($consignee)->governorate)->lng && optional(optional($consignee)->governorate)->lat) {
                $lng = optional($consignee)->governorate->lng;
                $lat = optional($consignee)->governorate->lat;
            }
        }

        if ($lng !== null && $lat !== null) {
            $zone = Zone::whereRaw(
                "coordinates IS NOT NULL AND ST_Contains(coordinates, ST_SRID(Point(?, ?), 4326))",
                [$lng, $lat]
            )->first();
            if ($zone)
                return $zone;
        }

        if ($stateId) {
            $zone = Zone::whereRaw(
                "coordinates IS NOT NULL AND ST_Intersects(coordinates, (SELECT polygon FROM states WHERE id = ?))",
                [$stateId]
            )->orderByRaw(
                    "CASE WHEN ST_GeometryType(ST_Intersection(coordinates, (SELECT polygon FROM states WHERE id = ?))) IN ('ST_Polygon','ST_MultiPolygon')
                  THEN ST_Area(ST_Intersection(coordinates, (SELECT polygon FROM states WHERE id = ?)))
                  ELSE 0 END DESC",
                    [$stateId, $stateId]
                )->first();

            if ($zone)
                return $zone;
        }

        if ($governorateId) {
            $zone = Zone::whereRaw(
                "coordinates IS NOT NULL AND ST_Intersects(coordinates, (SELECT polygon FROM governorates WHERE id = ?))",
                [$governorateId]
            )->orderByRaw(
                    "CASE WHEN ST_GeometryType(ST_Intersection(coordinates, (SELECT polygon FROM governorates WHERE id = ?))) IN ('ST_Polygon','ST_MultiPolygon')
                  THEN ST_Area(ST_Intersection(coordinates, (SELECT polygon FROM governorates WHERE id = ?)))
                  ELSE 0 END DESC",
                    [$governorateId, $governorateId]
                )->first();

            if ($zone) {
                return $zone;
            }
        }

        if ($useMerchantDestination && $consignee && method_exists($consignee, 'zone')) {
            $fallbackZone = $consignee->zone();
            if ($fallbackZone) {
                return Zone::find($fallbackZone->id) ?? $fallbackZone;
            }
        }

        return null;
    }

    private function shouldResolveReturnToMerchant(Shipment $shipment): bool
    {
        if (!(bool) $shipment->is_return) {
            return false;
        }

        $status = strtoupper(trim((string) ($shipment->status ?? '')));
        $pickupLegStatuses = [
            '',
            ShipmentStatusEnum::CREATED,
            ShipmentStatusEnum::TO_PICKUP,
        ];

        return !in_array($status, $pickupLegStatuses, true);
    }

    /**
     * Process inbound shipment sorting
     * Purpose: this function is used on those parcels which are created inside the station and requires collection.
     * @param Request $request Requires 'tracking_no' parameter
     * @return \Illuminate\Http\JsonResponse
     *   - 200: SorterResource with action/zone details
     *   - 422: Zone validation errors
     *   - 500: System errors
     * Determines routing based on zone ownership:
     * - Same owner: Direct to dispatch
     * - Different owner: Create transfer shipment
     * Creates ORDER_INBOUNDED and ORDER_SORTED histories
     */

    public function inbound_sort(Request $request, ParcelShelfService $parcelShelf)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('Sorter')) {
            return sendResponse(
                "Unauthorized: Only sorters can perform inbound sort.",
                [],
                false,
                ["You do not have permission to perform this action."],
                403
            );
        }

        $request->validate(['tracking_no' => 'required|exists:shipments,tracking_no']);

        $trackingNo = trim($request->tracking_no);

        // Normalize tracking number
        $originalTrackingNo = $trackingNo;
        if (strlen($trackingNo) > 2 && substr($trackingNo, 0, 2) === 'PE') {
            $withoutPE = substr($trackingNo, 2);
            $nextTwo = substr($withoutPE, 0, 2);
            if ($nextTwo === 'DR' || $nextTwo === 'ME') {
                $shipment = Shipment::withoutGlobalScope(\App\Models\Scopes\ExcludeReturnShipmentsScope::class)
                    ->with(['shipment_information', 'consignee', 'deliveryAddress'])
                    ->where('tracking_no', $withoutPE)
                    ->first();
                if ($shipment) {
                    $trackingNo = $withoutPE;
                }
            }
        }

        DB::beginTransaction();
        try {
            if (!isset($shipment)) {
                $shipment = Shipment::withoutGlobalScope(\App\Models\Scopes\ExcludeReturnShipmentsScope::class)
                    ->with(['shipment_information', 'consignee', 'deliveryAddress'])
                    ->where('tracking_no', $trackingNo)
                    ->firstOrFail();
            }



            if (in_array(strtoupper((string) $shipment->status), [ShipmentStatusEnum::RTO, ShipmentStatusEnum::RTO_PICKED, ShipmentStatusEnum::RTO_LOADED], true)) {
                DB::rollBack();
                return sendResponse(
                    "RTO shipment detected. Use the RTO return flow.",
                    [],
                    false,
                    ["RTO shipments must return to origin and cannot be inbound-sorted for delivery."],
                    422
                );
            }
            $shipmentHistory = ShipmentHistory::where("originActionName", "SORT")->where("shipment_id", $shipment->id)->first();
            if (isset($shipmentHistory)) {

                DB::rollBack();
                return sendResponse("Shipment is already in inbounded.", new SorterResource([
                    "tracking_no" => $shipment->tracking_no,
                    "action" => "MOVE_TO_SUPERVISOR",
                ]));
            }


            $originalShipmentTrackingNo = $shipment->tracking_no;

            // All your validation checks...
            if ($shipment->in_exception) {
                DB::rollBack();
                return sendResponse(
                    "This Shipment has an exception please use sort ofd function",
                    [],
                    false,
                    ["This Shipment has an exception please use sort ofd function"],
                    500
                );
            }

            if (!$user->owner_id || !$user->owner_type) {
                throw new \Exception("User workspace context is missing.");
            }

            if (strtolower(optional($shipment->coreStatus())->name) === "delivered") {
                DB::rollBack();
                return sendResponse("Shipment is already delivered.", new SorterResource([
                    "tracking_no" => $shipment->tracking_no,
                    "action" => "MOVE_TO_SUPERVISOR",
                ]));
            }

            if ($parcelShelf->isOnShelf($shipment->tracking_no)) {
                DB::rollBack();
                return sendResponse("", [], false, ["Parcel is on the shelf please use stock_out or pick_rto sort"], 500);
            }

            $zone = $this->resolveZoneForShipment($shipment);
            if (!$zone) {
                DB::rollBack();
                return sendResponse("", [], false, ["This shipment doesn't have any zone."], 500);
            }

            if (!$shipment->shipment_information) {
                $shipment->shipment_information()->create([
                    'shipment_id' => $shipment->id,
                    'zone_id' => null,
                    'in_warehouse' => false,
                    'tracking_no' => $shipment->tracking_no,
                ]);
                $shipment->load('shipment_information');
            }

            $zone = Zone::find($zone->id);
            $zoneOwner = $zone?->owner;
            $userOwner = $user->owner;

            if (!$zone || !$zoneOwner || !$userOwner) {
                DB::rollBack();
                return sendResponse("", [], false, ["There is a problem with zone or user owner."], 422);
            }

            $shipment->shipment_information->zone_id = $zone->id;
            $shipment->shipment_information->save();

            if ($zoneOwner->is($userOwner)) {
                $action = ShipmentStatusEnum::MOVE_TO_DISPATCH;
                ZoneShipment::firstOrCreate([
                    'zone_id' => $zone->id,
                    'shipment_tracking_no' => $shipment->tracking_no
                ]);
                $area = $zone->name;
                $sort_description = "Move to Dispatch: Zone - " . $zone->name;
            } else {
                $action = ShipmentStatusEnum::MOVE_TO_AREA;
                TransferShipment::firstOrCreate([
                    'owner_id' => $zone->owner_id,
                    'owner_type' => $zone->owner_type,
                    'shipment_tracking_no' => $shipment->tracking_no,
                ]);
                $area = $zone->owner->name;
                $sort_description = "This shipment belongs to " . $zone->owner->name;
            }

            // Histories
            $status1 = ShipmentStatusEnum::ORDER_COLLECTED;
            $timestamp = operation_now()->addSeconds(10)->toDateTimeString();

            shipmentHistory([
                "description" => status($status1)['description'],
                "shipment_id" => $shipment->id,
                "status" => status($status1)['label'],
                "time" => $timestamp
            ]);

            if (!$shipment->shipment_information->in_warehouse) {
                $inboundStatus = ShipmentStatusEnum::ORDER_INBOUNDED;
                shipmentHistory([
                    "status" => status($inboundStatus)['label'],
                    "description" => status($inboundStatus)['description'],
                    "shipment_id" => $shipment->id,
                ]);
            }

            // Update current hub and track previous location
            $prevOwnerId = $shipment->owner_id;
            $hubService = app(\App\Services\HubInformationService::class);
            $hubService->updateCurrentHub($shipment, facility("type"), facility("id"));

            $shipment->shipment_information->in_warehouse = true;
            $shipment->shipment_information->save();

            $shipment->is_sorted = true;

            if ($originalShipmentTrackingNo && (str_starts_with($originalShipmentTrackingNo, 'DR') || str_starts_with($originalShipmentTrackingNo, 'ME'))) {
                $shipment->tracking_no = $originalShipmentTrackingNo;
            }

            $shipment->save();

            $sortStatus = ShipmentStatusEnum::ORDER_SORTED;
            shipmentHistory([
                "status" => status($sortStatus)['label'],
                "description" => $sort_description,
                "shipment_id" => $shipment->id,
            ]);
            updateShipmentStatus($shipment->id, status($sortStatus)['label']);

            // CORRECTED: Get pickup driver from MerchantPickupShipment
            // Check all possible identifiers: ID, Tracking No, Pre-ID
            $pickupShipmentTask = MerchantPickupShipment::where(function ($query) use ($shipment) {
                $query->where('shipment_id', $shipment->id)
                    ->orWhere('shipment_tracking_no', $shipment->tracking_no);

                if ($shipment->pre_id) {
                    $query->orWhere('pre_id', $shipment->pre_id)
                        ->orWhere('shipment_tracking_no', $shipment->pre_id);
                }
            })->first();

            if (isset($pickupShipmentTask)) {

                $pickupTaskTransaction = PickuptaskTransaction::where("pickuptask_id", $pickupShipmentTask->pickup_task_id)->first();

                if (isset($pickupTaskTransaction) && !$pickupTaskTransaction->remitted && $pickupTaskTransaction->amount > 0) {
                    DB::rollBack();
                    return sendResponse("Pickup Task is not remitted yet.", new SorterResource([
                        "tracking_no" => $shipment->tracking_no,
                        "action" => "MOVE_TO_CASHIER",
                    ]));
                }

            }


            $isActuallyPickedUp = $pickupShipmentTask
                && $pickupShipmentTask->driver_id
                && $pickupShipmentTask->status === \App\Enums\MerchantPickupTaskStatusEnum::PICKED;

            if ($isActuallyPickedUp) {
                $pickupDriverId = $pickupShipmentTask->driver_id;

                Log::info("Adding pickup bonus for pickup driver", [
                    "driver_id" => $pickupDriverId,
                    "pickup_status" => $pickupShipmentTask->status,
                    "pickup_request_id" => $pickupShipmentTask->pickup_request_id,
                    "pickup_task_id" => $pickupShipmentTask->pickup_task_id,
                    "tracking_no" => $shipment->tracking_no,
                ]);

                try {
                    ShipmentPickupFactory::addPickupBonus($shipment, $pickupDriverId);
                } catch (\Throwable $bonusEx) {
                    Log::warning('Pickup bonus creation on inbound failed', [
                        'tracking_no' => $shipment->tracking_no,
                        'driver_id' => $pickupDriverId,
                        'pickup_task_id' => $pickupShipmentTask->pickup_task_id,
                        'error' => $bonusEx->getMessage(),
                    ]);
                }
            } else {
                Log::info("Skipping pickup bonus - not picked up or no record", [
                    "tracking_no" => $shipment->tracking_no,
                    "pickup_status" => $pickupShipmentTask?->status ?? 'no_record',
                    "pickup_driver_id" => $pickupShipmentTask?->driver_id ?? null,
                ]);
            }

            // Fee allocation
            try {
                $firstWarehouseId = (int) facility('id');
                $candidateOthers = collect([
                    $prevOwnerId,
                    $shipment->from_hub_id ?? null,
                    $shipment->current_hub_id ?? null,
                    $shipment->final_hub_id ?? null,
                ])->filter()->unique()->values();

                $otherWarehouseIds = $candidateOthers
                    ->reject(fn($wid) => (int) $wid === $firstWarehouseId)
                    ->values()
                    ->all();

                if (!isset($shipment->delivery_fee)) {
                    $shipment->delivery_fee = (float) optional($shipment->shipment_delivery)->delivery_fee ?? 0.0;
                }

                $ids = [
                    'first_warehouse_id' => $firstWarehouseId,
                    'pickup_driver_id' => $pickupShipmentTask->driver_id ?? null, // Use the correct pickup driver
                    'delivery_driver_id' => $shipment->driver_id, // Delivery driver
                ];

                $stateId = $shipment->state_id
                    ?? optional($shipment->deliveryAddress)->state_id
                    ?? optional($shipment->consignee)->state_id;

                app(FeeAllocator::class)->allocateForShipment(
                    $shipment,
                    $ids,
                    $otherWarehouseIds,
                    false,
                    $stateId,
                    true
                );
            } catch (\Throwable $allocEx) {
                logger()->warning('Fee allocation on inbound failed', [
                    'tracking_no' => $shipment->tracking_no,
                    'error' => $allocEx->getMessage(),
                ]);
            }

            // Update pickup task status (with null checks) - FIXED
            if ($pickupShipmentTask && $pickupShipmentTask->pickup_task) {
                // Check if all shipments in this pickup task are sorted
                $allSorted = $pickupShipmentTask->pickup_task->shipments()
                    ->whereHas('shipment', function ($q) {
                        $q->where('status', '!=', 'SORT');
                    })
                    ->doesntExist();

                if ($allSorted) {
                    $pickupShipmentTask->pickup_task->update(["status" => "pickup_completed"]);
                    Log::info("Pickup task marked as completed", [
                        'pickup_task_id' => $pickupShipmentTask->pickup_task->id,
                        'tracking_no' => $shipment->tracking_no,
                    ]);
                }
            }

            DB::commit();

            return sendResponse(
                "Shipment sorted.",
                new SorterResource([
                    'shipment' => $shipment->fresh('shipment_information', 'consignee', 'deliveryAddress'),
                    'action' => $action,
                    'area' => $area
                ]),
                true,
                [],
                200
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Inbound sort failed', [
                'tracking_no' => $trackingNo ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return sendResponse(
                "An error occurred while processing the inbound sort.",
                [],
                false,
                [$e->getMessage()],
                500
            );
        }
    }
    // public function inbound_sort(Request $request, ParcelShelfService $parcelShelf)
    // {
    //     $user = Auth::user();
    //     if (!$user || !$user->hasRole('Sorter')) {
    //         return sendResponse(
    //             "Unauthorized: Only sorters can perform inbound sort.",
    //             [],
    //             false,
    //             ["You do not have permission to perform this action."],
    //             403
    //         );
    //     }

    //     $request->validate(['tracking_no' => 'required|exists:shipments,tracking_no']);

    //     $trackingNo = trim($request->tracking_no);

    //     // Normalize tracking number: if it starts with PEDR or PEME, try without PE prefix first
    //     $originalTrackingNo = $trackingNo;
    //     if (strlen($trackingNo) > 2 && substr($trackingNo, 0, 2) === 'PE') {
    //         $withoutPE = substr($trackingNo, 2);
    //         $nextTwo = substr($withoutPE, 0, 2);
    //         if ($nextTwo === 'DR' || $nextTwo === 'ME') {
    //             // Try to find shipment with DR/ME prefix first
    //             $shipment = Shipment::with(['shipment_information', 'consignee', 'deliveryAddress'])
    //                 ->where('tracking_no', $withoutPE)
    //                 ->first();
    //             if ($shipment) {
    //                 $trackingNo = $withoutPE;
    //             }
    //         }
    //     }

    //     DB::beginTransaction();
    //     try {
    //         /** @var Shipment $shipment */
    //         if (!isset($shipment)) {
    //             $shipment = Shipment::with(['shipment_information', 'consignee', 'deliveryAddress'])->where('tracking_no', $trackingNo)->firstOrFail();
    //         }

    //         // Ensure tracking_no is not modified - preserve original DR/ME prefixes
    //         $originalShipmentTrackingNo = $shipment->tracking_no;

    //         if ($shipment->in_exception) {
    //             return sendResponse(
    //                 "This Shipment has an exception please use sort ofd function",
    //                 [],
    //                 false,
    //                 ["This Shipment has an exception please use sort ofd function"],
    //                 500
    //             );
    //         }

    //         if (!$user->owner_id || !$user->owner_type) {
    //             throw new \Exception("User workspace context is missing.");
    //         }

    //         if (strtolower(optional($shipment->coreStatus())->name) === "delivered") {
    //             return sendResponse("Shipment is already delivered.", new SorterResource([
    //                 "tracking_no" => $shipment->tracking_no,
    //                 "action" => "MOVE_TO_SUPERVISOR",
    //             ]));
    //         }

    //         if ($parcelShelf->isOnShelf($shipment->tracking_no)) {
    //             return sendResponse("", [], false, ["Parcel is on the shelf please use stock_out or pick_rto sort"], 500);
    //         }

    //         $zone = $this->resolveZoneForShipment($shipment);
    //         if (!$zone) {
    //             DB::rollBack();
    //             return sendResponse("", [], false, ["This shipment doesn't have any zone."], 500);
    //         }

    //         if (!$shipment->shipment_information) {
    //             $shipment->shipment_information()->create([
    //                 'shipment_id' => $shipment->id,
    //                 'zone_id' => null,
    //                 'in_warehouse' => false,
    //                 'tracking_no' => $shipment->tracking_no,
    //             ]);
    //             $shipment->load('shipment_information');
    //         }

    //         $zone = Zone::find($zone->id);
    //         $zoneOwner = $zone?->owner;
    //         $userOwner = $user->owner;

    //         if (!$zone || !$zoneOwner || !$userOwner) {
    //             DB::rollBack();
    //             return sendResponse("", [], false, ["There is a problem with zone or user owner."], 422);
    //         }

    //         $shipment->shipment_information->zone_id = $zone->id;
    //         $shipment->shipment_information->save();

    //         if ($zoneOwner->is($userOwner)) {
    //             $action = ShipmentStatusEnum::MOVE_TO_DISPATCH;
    //             ZoneShipment::firstOrCreate([
    //                 'zone_id' => $zone->id,
    //                 'shipment_tracking_no' => $shipment->tracking_no
    //             ]);
    //             $area = $zone->name;
    //             $sort_description = "Move to Dispatch: Zone - " . $zone->name;
    //         } else {
    //             $action = ShipmentStatusEnum::MOVE_TO_AREA;
    //             TransferShipment::firstOrCreate([
    //                 'owner_id' => $zone->owner_id,
    //                 'owner_type' => $zone->owner_type,
    //                 'shipment_tracking_no' => $shipment->tracking_no,
    //             ]);
    //             $area = $zone->owner->name;
    //             $sort_description = "This shipment belongs to " . $zone->owner->name;
    //         }

    //         // Histories
    //         $status1 = ShipmentStatusEnum::ORDER_COLLECTED;
    //         $timestamp = operation_now()->addSeconds(10)->toDateTimeString();

    //         shipmentHistory([
    //             "description" => status($status1)['description'],
    //             "shipment_id" => $shipment->id,
    //             "status" => status($status1)['label'],
    //             "time" => $timestamp
    //         ]);

    //         if (!$shipment->shipment_information->in_warehouse) {
    //             $inboundStatus = ShipmentStatusEnum::ORDER_INBOUNDED;
    //             shipmentHistory([
    //                 "status" => status($inboundStatus)['label'],
    //                 "description" => status($inboundStatus)['description'],
    //                 "shipment_id" => $shipment->id,
    //             ]);
    //         }

    //         // خزّن الـ warehouse الحالي
    //         $prevOwnerId = $shipment->owner_id;
    //         $prevOwnerType = $shipment->owner_type;

    //         $shipment->shipment_information->in_warehouse = true;
    //         $shipment->shipment_information->save();

    //         $shipment->owner_type = facility("type");
    //         $shipment->owner_id = facility("id");
    //         $shipment->is_sorted = true;

    //         // Preserve original tracking_no if it starts with DR or ME (don't add PE prefix)
    //         if ($originalShipmentTrackingNo && (str_starts_with($originalShipmentTrackingNo, 'DR') || str_starts_with($originalShipmentTrackingNo, 'ME'))) {
    //             $shipment->tracking_no = $originalShipmentTrackingNo;
    //         }

    //         $shipment->save();

    //         // $sortStatus = "ORDER_SORTED";
    //         $sortStatus = ShipmentStatusEnum::ORDER_SORTED;
    //         shipmentHistory([
    //             "status" => status($sortStatus)['label'],
    //             "description" => $sort_description,
    //             "shipment_id" => $shipment->id,
    //         ]);
    //         updateShipmentStatus($shipment->id, status($sortStatus)['label']);

    //         if ($shipment->driver_id) {
    //             Log::info("Adding pickup bonus for driver", [
    //                 "driver_id" => $shipment->driver_id,
    //                 "tracking_no" => $shipment->tracking_no,
    //             ]);
    //             try {
    //                 ShipmentPickupFactory::addPickupBonus($shipment, $shipment->driver_id);
    //             } catch (\Throwable $bonusEx) {
    //                 Log::warning('Pickup bonus creation on inbound failed', [
    //                     'tracking_no' => $shipment->tracking_no,
    //                     'driver_id' => $shipment->driver_id,
    //                     'error' => $bonusEx->getMessage(),
    //                 ]);
    //             }
    //         }

    //         try {
    //             $firstWarehouseId = (int) facility('id');
    //             $candidateOthers = collect([
    //                 $prevOwnerId,
    //                 $shipment->from_hub_id ?? null,
    //                 $shipment->current_hub_id ?? null,
    //                 $shipment->final_hub_id ?? null,
    //             ])->filter()->unique()->values();

    //             $otherWarehouseIds = $candidateOthers
    //                 ->reject(fn($wid) => (int) $wid === $firstWarehouseId)
    //                 ->values()
    //                 ->all();

    //             if (!isset($shipment->delivery_fee)) {
    //                 $shipment->delivery_fee = (float) optional($shipment->shipment_delivery)->delivery_fee ?? 0.0;
    //             }

    //             $ids = [
    //                 'first_warehouse_id' => $firstWarehouseId,
    //                 'pickup_driver_id' => null,
    //                 'delivery_driver_id' => null,
    //             ];

    //             $stateId = $shipment->state_id
    //                 ?? optional($shipment->deliveryAddress)->state_id
    //                 ?? optional($shipment->consignee)->state_id;

    //             app(FeeAllocator::class)->allocateForShipment(
    //                 $shipment,
    //                 $ids,
    //                 $otherWarehouseIds,
    //                 false,
    //                 $stateId,
    //                 true
    //             );
    //         } catch (\Throwable $allocEx) {
    //             logger()->warning('Fee allocation on inbound failed', [
    //                 'tracking_no' => $shipment->tracking_no,
    //                 'error' => $allocEx->getMessage(),
    //             ]);
    //         }

    //         // // Create pickup bonus (inactive) if shipment has a pickup driver
    //         // if ($shipment->pickup_driver_id) {
    //         //     try {
    //         //         \App\Domain\Pickup\ShipmentPickupFactory::addPickupBonus($shipment, $shipment->pickup_driver_id);
    //         //     } catch (\Throwable $bonusEx) {
    //         //         logger()->warning('Pickup bonus creation on inbound failed', [
    //         //             'tracking_no' => $shipment->tracking_no,
    //         //             'pickup_driver_id' => $shipment->pickup_driver_id,
    //         //             'error' => $bonusEx->getMessage(),
    //         //         ]);
    //         //     }
    //         // }
    //         $task = MerchantPickupShipment::where("shipment_tracking_no", $shipment->tracking_no)->first();
    //         $allSorted = $task->pickup_task->shipments
    //             ->load('shipment')
    //             ->pluck('shipment.status')
    //             ->every(fn($s) => $s === 'SORT');
    //         if ($allSorted)
    //             $task->pickup_task->update(["status" => "pickup_completed"]);
    //         DB::commit();

    //         return sendResponse(
    //             "Shipment sorted.",
    //             new SorterResource(['shipment' => $shipment->fresh('shipment_information', 'consignee', 'deliveryAddress'), 'action' => $action, 'area' => $area]),
    //             true,
    //             [],
    //             200
    //         );

    //     } catch (\Throwable $e) {
    //         DB::rollBack();
    //         return sendResponse("An error occurred while processing the inbound sort.", [], false, [$e->getMessage()], 500);
    //     }
    // }




    /**
     * Load shipment onto transfer vehicle
     * this function is used to load the parcels to truck. which are the part of transfer.
     * @param Request $request Requires:
     *   - tracking_no
     *   - transfer_task_id
     *   - transfer_destination_id
     * @return \Illuminate\Http\JsonResponse
     *   - 200: Loading confirmation with remaining count
     *   - 422: Invalid transfer assignment
     *   - 500: Loading errors
     * Updates transfer task status when complete
     * Creates ORDER_LOADED history with route details
     */
    public function load(Request $request, ShipmentValidationService $validationService)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('Sorter')) {
            return sendResponse(
                "Unauthorized: Only sorters can perform load.",
                [],
                false,
                ["You do not have permission to perform this action."],
                403
            );
        }
        $request->validate([
            'tracking_no' => 'required', // Removed exists:shipments,tracking_no to allow containers
            'transfer_task_id' => 'required|exists:transfer_tasks,id',
            'transfer_destination_id' => 'required|exists:transfer_destinations,id',
            'truck_barcode' => 'required|exists:trucks,barcode',
        ]);

        DB::beginTransaction();
        try {
            $trackingNo = trim($request->tracking_no);
            
            // Check if it's a container
            $container = \App\Models\Container::where('tracking_no', $trackingNo)
                ->first();

            if ($container) {
                // Determine shipments to load: use currentShipments (items currently logically inside)
                // Use a fresh query to ensure we get the latest state
                $shipmentsToLoad = $container->currentShipments;

                if ($shipmentsToLoad->isEmpty()) {
                    return sendResponse("Container is empty.", [], false, ["Container has no shipments to load."], 422);
                }

                $loadedCount = 0;
                $errors = [];
                
                $truck = Truck::where('barcode', $request->truck_barcode)->first();
                if (!$truck) {
                    return sendResponse("Invalid truck barcode.", [], false, ["Truck not found."], 422);
                }

                $transferTask = TransferTask::find($request->transfer_task_id);
                if (!$transferTask) {
                    return sendResponse("", [], false, ["Transfer task not found."], 422);
                }
                
                // Update task truck if needed
                $transferTask->truck_id = $truck->id;
                $transferTask->save();

                 if ($transferTask->truck_id !== $truck->id) {
                    return sendResponse('Invalid truck for this task.', [], false, ["Scanned truck ({$truck->barcode}) is not assigned to task #{$transferTask->id}"], 422);
                }
                
                foreach ($shipmentsToLoad as $shipment) {
                    try {
                        // Reuse logic for single shipment load
                        
                        if (strtolower($shipment->coreStatus()->name) == strtolower("delivered") || $shipment->status == strtolower("delivered")) {
                            $failedTrackingNos[] = $shipment->tracking_no;
                            continue;
                        }

                        if ($shipment->in_exception) {
                             $errors[] = "Shipment {$shipment->tracking_no}: Has exception.";
                             continue;
                        }

                        if ($validationService->isLoadedFromSortFacility($shipment->tracking_no)) {
                             $errors[] = "Shipment {$shipment->tracking_no}: Cannot be loaded from this facility.";
                             continue;
                        }
                        
                        $transferTaskShipment = TransferTaskShipment::where("shipment_tracking_no", $shipment->tracking_no)
                            ->where('transfer_task_id', $request->transfer_task_id)
                            ->where('transfer_destination_id', $request->transfer_destination_id)
                            ->first();

                        if (!$transferTaskShipment) {
                             $errors[] = "Shipment {$shipment->tracking_no}: Not part of this transfer.";
                             continue;
                        }
                        
                        $info = $shipment->shipment_information;
                        if ($info) {
                            $info->in_warehouse = false;
                            $info->save();
                        }
                        
                        $loadStatus = ShipmentStatusEnum::ORDER_LOADED;
                        $sorter = Auth::user()->name;
                        shipmentHistory([
                            "status" => status($loadStatus)['label'],
                            "description" => "Shipment loaded onto truck (Task #{$request->transfer_task_id}) via Container {$container->code} - by {$sorter}.",
                            "shipment_id" => $shipment->id,
                            "data" => json_encode(["truck_barcode" => $request->truck_barcode, "container_code" => $container->code])
                        ]);

                        updateShipmentStatus($shipment->id, status($loadStatus)['label']);

                        TransferShipment::where('shipment_tracking_no', $shipment->tracking_no)->update(['owner_id' => null, 'owner_type' => null]);

                        TransferTaskShipment::where('shipment_tracking_no', $shipment->tracking_no)->update([
                            'status' => 'loaded',
                            'loaded_at' => operation_now(),
                        ]);
                        
                        $loadedCount++;

                    } catch (\Exception $e) {
                        $failedTrackingNos[] = $shipment->tracking_no;
                    }
                }
                
                if ($loadedCount === 0) {
                    DB::rollBack();
                    return sendResponse(
                        "Failed to load any shipments from container.",
                        [],
                        false,
                        $errors,
                        422
                    );
                }

                // Update Container Status
                $container->status = \App\Models\Container::STATUS_IN_TRANSFER;
                // $container->truck_id = $truck->id; // If container has truck_id
                $container->save();

                // Check pending count for the destination (same as single load)
                $pendingCount = TransferTaskShipment::where('transfer_task_id', $request->transfer_task_id)
                    ->where('transfer_destination_id', $request->transfer_destination_id)
                    ->where('status', 'pending')
                    ->count();

                if ($pendingCount === 0) {
                    TransferDestination::find($request->transfer_destination_id)->update([
                        'status' => 'loaded',
                        'loaded_at' => operation_now(),
                    ]);

                    TransferTask::whereDoesntHave('destinations', function ($query) {
                        $query->where('status', 'pending');
                    })->update(['status' => 'loaded']);
                }

                DB::commit();
                
                $msg = "Container loaded successfully. {$loadedCount} shipments processed for transfer.";

                if (!empty($failedTrackingNos)) {
                    $msg .= " Some shipments failed: " . implode(', ', array_unique($failedTrackingNos));
                }
                
                return sendResponse($msg, new SorterResource(["count" => $pendingCount, "container" => $container]));

            } else {
                // Must be a single Shipment
                $shipment = Shipment::where('tracking_no', $trackingNo)->first();
                if (!$shipment) {
                     return sendResponse("Shipment/Container not found.", [], false, ["Tracking number or Container code not found."], 404);
                }

                if (strtolower($shipment->coreStatus()->name) == strtolower("delivered") || $shipment->status == strtolower("delivered")) {
                    return sendResponse("Shipment is already delivered.", new SorterResource(["tracking_no" => $shipment->tracking_no, "action" => "MOVE_TO_SUPERVISOR",]));
                }
    
                if ($shipment->in_exception) {
                    return sendResponse("This Shipment has no exception.", [], false, ["This Shipment has an exception please use sort ofd function"], 500);
                }
    
                $truck = Truck::where('barcode', $request->truck_barcode)->first();
                if (!$truck) {
                    return sendResponse("Invalid truck barcode.", [], false, ["Truck not found."], 422);
                }
    
                $transferTask = TransferTask::find($request->transfer_task_id);
                if (!$transferTask) {
                    return sendResponse("", [], false, ["Transfer task not found."], 422);
                }
    
                $transferTask->truck_id = $truck->id;
                $transferTask->save();
    
                $transferTaskShipment = TransferTaskShipment::where("shipment_tracking_no", $request->tracking_no)
                    ->where('transfer_task_id', $request->transfer_task_id)
                    ->where('transfer_destination_id', $request->transfer_destination_id)
                    ->with('shipment.shipment_information')
                    ->first();
    
                if ($validationService->isLoadedFromSortFacility($request->tracking_no)) {
                    return sendResponse("", [], false, ["this shipment cannot be loaded from this facility."], 422);
                }
    
                if (!$transferTaskShipment) {
                    return sendResponse("", [], false, ["The specified shipment does not exist or is not part of this transfer."], 422);
                }
    
                $shipment = $transferTaskShipment->shipment;
                if (!$shipment) {
                    return sendResponse("", [], false, ["Shipment not found."], 422);
                }
    
                $info = $shipment->shipment_information;
                if (!$info) {
                    return sendResponse("", [], false, ["No shipment information found."], 422);
                }
    
                $truck = Truck::where('barcode', $request->truck_barcode)->firstOrFail();
                $transferTask = TransferTask::findOrFail($request->transfer_task_id);
    
                if ($transferTask->truck_id !== $truck->id) {
                    return sendResponse('Invalid truck for this task.', [], false, ["Scanned truck ({$truck->barcode}) is not assigned to task #{$transferTask->id}"], 422);
                }
    
    
                $info->in_warehouse = false;
                $info->save();
    
                // IMPORTANT: Load sort should NEVER change current_owner or from_owner!
                // These fields are only changed by inbound_sort and unload operations.
                // Log the current values for debugging purposes.
                \Log::info('Load sort - hub information fields should NOT change', [
                    'shipment_id' => $shipment->id,
                    'tracking_no' => $shipment->tracking_no,
                    'current_owner_type' => $shipment->current_owner_type,
                    'current_owner_id' => $shipment->current_owner_id,
                    'from_owner_type' => $shipment->from_owner_type,
                    'from_owner_id' => $shipment->from_owner_id,
                ]);
    
                $loadStatus = ShipmentStatusEnum::ORDER_LOADED;
                $sorter = Auth::user()->name;
                shipmentHistory([
                    "status" => status($loadStatus)['label'],
                    "description" => "Shipment loaded onto truck (Task #{$request->transfer_task_id}) - by {$sorter}.",
                    "shipment_id" => $shipment->id,
                    "data" => json_encode(["truck_barcode" => $request->truck_barcode])
                ]);
    
                updateShipmentStatus($shipment->id, status($loadStatus)['label']);
    
                TransferShipment::where('shipment_tracking_no', $request->tracking_no)->update(['owner_id' => null, 'owner_type' => null]);
    
                TransferTaskShipment::where('shipment_tracking_no', $request->tracking_no)->update([
                    'status' => 'loaded',
                    'loaded_at' => operation_now(),
                ]);
    
                $pendingCount = TransferTaskShipment::where('transfer_task_id', $request->transfer_task_id)
                    ->where('transfer_destination_id', $request->transfer_destination_id)
                    ->where('status', 'pending')
                    ->count();
    
                if ($pendingCount === 0) {
                    TransferDestination::find($request->transfer_destination_id)->update([
                        'status' => 'loaded',
                        'loaded_at' => operation_now(),
                    ]);
    
                    TransferTask::whereDoesntHave('destinations', function ($query) {
                        $query->where('status', 'pending');
                    })->update(['status' => 'loaded']);
                }
    
                DB::commit();
                return sendResponse("Shipment loaded successfully.", new SorterResource(["shipment" => $transferTaskShipment, "count" => $pendingCount]));
            }
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("An error occurred while loading the shipment.", [], false, [$e->getMessage()], 500);
        }
    }

    // public function load(Request $request)
    // {
    //     $request->validate([
    //         'tracking_no' => 'required|exists:shipments,tracking_no',
    //         'transfer_task_id' => 'required|exists:transfer_tasks,id',
    //         'transfer_destination_id' => 'required|exists:transfer_destinations,id',
    //     ]);

    //     DB::beginTransaction();
    //     try {
    //         $transferTaskShipment = TransferTaskShipment::where("shipment_tracking_no", $request->tracking_no)
    //             ->where('transfer_task_id', $request->transfer_task_id)
    //             ->where('transfer_destination_id', $request->transfer_destination_id)
    //             ->with('shipment.shipment_information')
    //             ->first();

    //         if (!$transferTaskShipment) {return sendResponse("", [], false, ["The specified shipment does not exist or is not part of this transfer."], 422);
    //         }

    //         $shipment = $transferTaskShipment->shipment;
    //         if (!$shipment) {
    //             return sendResponse("", [], false, ["Shipment not found."], 422);
    //         }

    //         $info = $shipment->shipment_information;
    //         if (!$info) {
    //             return sendResponse("", [], false, ["No shipment information found."], 422);
    //         }

    //         $info->in_warehouse = false;
    //         $info->save();

    //         $loadStatus = "ORDER_LOADED";
    //         $sorter = Auth::user()->name;
    //         shipmentHistory([
    //             "status" => status($loadStatus)['label'],
    //             "description" => "Shipment loaded onto truck (Task #{$request->transfer_task_id}) - (Origin - {$transferTaskShipment->destination->origin->name}) - (Destination - {$transferTaskShipment->destination->destination->name}) by ({$sorter}).",
    //             "shipment_id" => $shipment->id,
    //         ]);

    //         updateShipmentStatus($shipment->id, status($loadStatus)['label']);
    //         $transferTaskShipment->status = 'loaded';
    //         $transferTaskShipment->save();

    //         $pendingCount = TransferTaskShipment::where('transfer_task_id', $request->transfer_task_id)
    //             ->where('transfer_destination_id', $request->transfer_destination_id)
    //             ->where('status', 'pending')
    //             ->count();

    //         if ($pendingCount === 0) {
    //             TransferDestination::find($request->transfer_destination_id)->update([
    //                 'status' => 'loaded'
    //             ]);

    //             TransferTask::whereDoesntHave('destinations', function ($query) {
    //                 $query->where('status', 'pending');
    //             })->update(['status' => 'loaded']);
    //         }

    //         DB::commit();
    //         return sendResponse("Shipment loaded successfully.", new SorterResource(["shipment" => $transferTaskShipment, "count" => $pendingCount]));
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         return sendResponse("An error occurred while loading the shipment.", [], false, [$e->getMessage()], 500);
    //     }
    // }

    /**
     * Unload shipment at destination facility
     * this function will be used when the shipment has arrived at the destination to unload the shipment and update it's status and it's task.
     * @param Request $request Requires 'tracking_no'
     * @return \Illuminate\Http\JsonResponse
     *   - 200: Unload confirmation with next action
     *   - 403: Unauthorized facility
     *   - 422: Transfer record issues
     *   - 500: Unloading errors
     * Verifies destination facility authorization
     * Creates ORDER_UNLOADED and ORDER_INBOUNDED histories
     */
    public function unload(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('Sorter')) {
            return sendResponse(
                "Unauthorized: Only sorters can perform unload.",
                [],
                false,
                ["You do not have permission to perform this action."],
                403
            );
        }
        $request->validate([
            'tracking_no' => 'required', // Removed exists:shipments,tracking_no
            'truck_barcode' => 'required|exists:trucks,barcode',
        ]);

        DB::beginTransaction();
        try {
            $trackingNo = trim($request->tracking_no);
            
            // Check if it's a container
            $container = \App\Models\Container::where('tracking_no', $trackingNo)
                ->first();

            if ($container) {
                 // Determine shipments to unload: use currentShipments
                $shipmentsToUnload = $container->currentShipments;

                if ($shipmentsToUnload->isEmpty()) {
                    return sendResponse("Container is empty.", [], false, ["Container has no shipments to unload."], 422);
                }
                
                $unloadedCount = 0;
                $errors = [];
                // Get the truck
                $truck = Truck::where('barcode', $request->truck_barcode)->firstOrFail();
                $validationService = new ShipmentValidationService();
                
                 // Determine user's facility credentials (needed for authorization check inside loop)
                $sorterFacilityId = facility("id");
                $sorterFacilityType = facility("type");

                foreach ($shipmentsToUnload as $shipment) {
                     try {
                        if (($shipment->direction ?? null) === 'return_to_origin' || (bool) $shipment->is_return) {
                             $errors[] = "Shipment {$shipment->tracking_no}: Return leg detected.";
                             continue;
                        }

                        // Validation 3: Get and validate transfer task shipment
                        $transferTaskShipment = $validationService->getValidTransferTaskShipment($shipment->tracking_no);
                        if (!$transferTaskShipment) {
                             $errors[] = "Shipment {$shipment->tracking_no}: No transfer record found.";
                             continue;
                        }
                        
                         // Validation 11: Facility authorization check
                        $destinationFacilityId = $transferTaskShipment->destination->destination_id;
                        $destinationFacilityType = $transferTaskShipment->destination->destination_type;

                        $isAuthorized = $destinationFacilityId == $sorterFacilityId
                            && $destinationFacilityType == $sorterFacilityType;

                        // Proceed with unloading logic
                        $hubService = app(\App\Services\HubInformationService::class);
                        $hubService->updateCurrentHub($shipment, facility("type"), facility("id"));

                        $info = $shipment->shipment_information;
                        $info->in_warehouse = true;

                        $shipment->is_sorted = true;
                        $shipment->save();
                        $info->save();

                        $unloadStatus = ShipmentStatusEnum::ORDER_UNLOADED;
                        shipmentHistory([
                            "status" => status($unloadStatus)['label'],
                            "description" => "Shipment unloaded by (" . Auth::user()->name . ") via Container {$container->code}",
                            "shipment_id" => $shipment->id,
                        ]);

                        $zone = $shipment->shipment_information->zone;
                        $zoneOwnerId = $zone->owner_id;
                        $zoneOwnerType = $zone->owner_type;

                        $userOwnerId = facility("id");
                        $userOwnerType = facility("type");

                        $action = null;

                        if ($zoneOwnerId == $userOwnerId && $zoneOwnerType == $userOwnerType) {
                            ZoneShipment::firstOrCreate([
                                'zone_id' => $zone->id,
                                'shipment_tracking_no' => $shipment->tracking_no
                            ]);

                            $area = $zone->name;
                            $action = ShipmentStatusEnum::MOVE_TO_DISPATCH;
                            $sort_description = "Move to Dispatch: Zone - " . $zone->name;
                        } else {
                            TransferShipment::firstOrCreate([
                                'owner_id' => $destinationFacilityId,
                                'owner_type' => $destinationFacilityType,
                                'shipment_tracking_no' => $shipment->tracking_no,
                            ]);
                            $area = $zone->name;
                            $action = ShipmentStatusEnum::MOVE_TO_AREA;
                            $sort_description = "Move to Transfer Area for destination facility";
                        }
                        
                        $sortStatus = $action;
                        shipmentHistory([
                            "status" => status($sortStatus)['label'],
                            "description" => $sort_description,
                            "shipment_id" => $shipment->id,
                        ]);
    
                        updateShipmentStatus($shipment->id, status($sortStatus)['label']);
    
                        $transferTaskShipment->status = 'unloaded';
                        $transferTaskShipment->save();

                        $pendingCount = TransferTaskShipment::where('transfer_task_id', $transferTaskShipment->transfer_task_id)
                            ->where('transfer_destination_id', $transferTaskShipment->transfer_destination_id)
                            ->where(function ($q) {
                                $q->where('status', 'loaded')
                                    ->orWhere('status', 'pending');
                            })
                            ->count();
    
                        if ($pendingCount === 0) {
                            TransferDestination::find($transferTaskShipment->transfer_destination_id)->update([
                                'status' => 'unloaded',
                                'loaded_at' => operation_now(),
                            ]);
    
                            TransferTask::whereDoesntHave('destinations', function ($q) {
                                $q->where(function ($q2) {
                                    $q2->where('status', 'loaded')
                                        ->orWhere('status', 'pending');
                                });
                            })->update(['status' => 'unloaded']);
                        }
                        
                        $unloadedCount++;

                     } catch (\Exception $e) {
                         $errors[] = "Shipment {$shipment->tracking_no}: " . $e->getMessage();
                     }
                }
                
                if ($unloadedCount === 0) {
                     DB::rollBack();
                     return sendResponse("Failed to unload any shipments from container.", [], false, $errors, 422);
                }

                // Update Container Status
                $container->status = \App\Models\Container::STATUS_UNLOADED; // Or ARRIVED
                // $container->current_hub_id = facility('id'); // Logic to update hub
                $container->save();

                DB::commit();
                
                $msg = "Container unloaded successfully. {$unloadedCount} shipments processed for transfer.";
                if (count($errors) > 0) {
                    $msg .= " Some shipments failed: " . implode(", ", $errors);
                }
                
                 return sendResponse($msg, new SorterResource(["count" => $unloadedCount, "container" => $container]));

            } else {
                // Determine user's facility credentials (needed for authorization check inside loop)
                $sorterFacilityId = facility("id");
                $sorterFacilityType = facility("type");
                
                // Get the shipment with relationships
                $shipment = Shipment::where('tracking_no', $trackingNo)->firstOrFail();
    
                if (($shipment->direction ?? null) === 'return_to_origin' || (bool) $shipment->is_return) {
                    return sendResponse(
                        "Return leg detected. Use return intake flow.",
                        [],
                        false,
                        ["Return legs must not enter outbound dispatch routing."],
                        422
                    );
                }
    
                // Get the truck
                $truck = Truck::where('barcode', $request->truck_barcode)->firstOrFail();
    
                // Initialize validation service
                $validationService = new ShipmentValidationService();
    
                // Validation 3: Get and validate transfer task shipment
                $transferTaskShipment = $validationService->getValidTransferTaskShipment($trackingNo);
                if (!$transferTaskShipment) {
                    return sendResponse("No transfer record found for this shipment.", [], false, ["No transfer record found for this shipment."], 422);
                }
    
    
                // Validation 11: Facility authorization check
                $destinationFacilityId = $transferTaskShipment->destination->destination_id;
                $destinationFacilityType = $transferTaskShipment->destination->destination_type;
    
                // $sorterFacilityId = facility("id");
                // $sorterFacilityType = facility("type");
    
                $isAuthorized = $destinationFacilityId == $sorterFacilityId
                    && $destinationFacilityType == $sorterFacilityType;
    
                // if (!$isAuthorized) {
                //     return sendResponse(
                //         "Unauthorized unloading attempt.",
                //         [],
                //         false,
                //         ["This facility is not authorized to unload this shipment"],
                //         403
                //     );
                // }
    
                // All validations passed, proceed with unloading
                // Update current hub and track previous location
                $hubService = app(\App\Services\HubInformationService::class);
                $hubService->updateCurrentHub($shipment, facility("type"), facility("id"));
    
                $info = $shipment->shipment_information;
                $info->in_warehouse = true;
    
                $shipment->is_sorted = true;
                $shipment->save();
                $info->save();
    
                $unloadStatus = ShipmentStatusEnum::ORDER_UNLOADED;
                shipmentHistory([
                    "status" => status($unloadStatus)['label'],
                    "description" => "Shipment unloaded by (" . Auth::user()->name . ")",
                    "shipment_id" => $shipment->id,
                ]);
    
                $zone = $shipment->shipment_information->zone;
                $zoneOwnerId = $zone->owner_id;
                $zoneOwnerType = $zone->owner_type;
    
                $userOwnerId = facility("id");
                $userOwnerType = facility("type");
    
                $action = null;
    
                if ($zoneOwnerId == $userOwnerId && $zoneOwnerType == $userOwnerType) {
                    ZoneShipment::firstOrCreate([
                        'zone_id' => $zone->id,
                        'shipment_tracking_no' => $shipment->tracking_no
                    ]);
    
                    $area = $zone->name;
                    $action = ShipmentStatusEnum::MOVE_TO_DISPATCH;
                    $sort_description = "Move to Dispatch: Zone - " . $zone->name;
                } else {
                    TransferShipment::firstOrCreate([
                        'owner_id' => $destinationFacilityId,
                        'owner_type' => $destinationFacilityType,
                        'shipment_tracking_no' => $shipment->tracking_no,
                    ]);
                    $area = $zone->name;
                    $action = ShipmentStatusEnum::MOVE_TO_AREA;
                    $sort_description = "Move to Transfer Area for destination facility";
                }
    
                $sortStatus = $action;
                shipmentHistory([
                    "status" => status($sortStatus)['label'],
                    "description" => $sort_description,
                    "shipment_id" => $shipment->id,
                ]);
    
                updateShipmentStatus($shipment->id, status($sortStatus)['label']);
    
                $transferTaskShipment->status = 'unloaded';
                $transferTaskShipment->save();
    
                $pendingCount = TransferTaskShipment::where('transfer_task_id', $transferTaskShipment->transfer_task_id)
                    ->where('transfer_destination_id', $transferTaskShipment->transfer_destination_id)
                    ->where(function ($q) {
                        $q->where('status', 'loaded')
                            ->orWhere('status', 'pending');
                    })
                    ->count();
    
                if ($pendingCount === 0) {
                    TransferDestination::find($transferTaskShipment->transfer_destination_id)->update([
                        'status' => 'unloaded',
                        'loaded_at' => operation_now(),
                    ]);
    
                    TransferTask::whereDoesntHave('destinations', function ($q) {
                        $q->where(function ($q2) {
                            $q2->where('status', 'loaded')
                                ->orWhere('status', 'pending');
                        });
                    })->update(['status' => 'unloaded']);
                }
    
                DB::commit();
                return sendResponse("Shipment unloaded successfully.", new SorterResource([
                    "shipment" => $transferTaskShipment,
                    "action" => $action,
                    "area" => $area,
                ]));
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return sendResponse("Shipment or truck not found.", [], false, [$e->getMessage()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("An error occurred while unloading the shipment.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * Sort shipment within warehouse
     * This function is just for normal use. this function will not change the status of the parcel and will just tell the zone or transfer area.
     * @param Request $request Requires 'tracking_no'
     * @return \Illuminate\Http\JsonResponse
     *   - 200: Sorting result with zone details
     *   - 422: Zone validation issues
     *   - 500: Sorting errors
     * Determines final dispatch/transfer routing
     * Creates ORDER_SORTED history
     */
    public function warehouse_sort(Request $request, SorterService $sorterService)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('Sorter')) {
            return sendResponse(
                "Unauthorized: Only sorters can perform warehouse sort.",
                [],
                false,
                ["You do not have permission to perform this action."],
                403
            );
        }
        $request->validate(['tracking_no' => 'required|exists:shipments,tracking_no']);

        $trackingNo = trim($request->tracking_no);
        try {
            $shipment = Shipment::withoutGlobalScope(\App\Models\Scopes\ExcludeReturnShipmentsScope::class)
                ->where('tracking_no', $trackingNo)
                ->firstOrFail();

            if (strtolower($shipment->coreStatus()->name) == "delivered") {
                return sendResponse("Shipment is already delivered.", new SorterResource(["tracking_no" => $shipment->tracking_no, "action" => "MOVE_TO_SUPERVISOR",]));
            }

            if ($shipment->in_exception) {
                return sendResponse("This Shipment has an exception.", [], false, ["This Shipment has an exception please use sort ofd function"], 500);
            }

            if (in_array(strtoupper((string) $shipment->status), [ShipmentStatusEnum::RTO, ShipmentStatusEnum::RTO_PICKED, ShipmentStatusEnum::RTO_LOADED], true)) {
                return sendResponse(
                    "RTO shipment detected. Use the RTO return flow.",
                    [],
                    false,
                    ["RTO shipments must return to origin and cannot be routed to outbound dispatch."],
                    422
                );
            }

            $resolvedZone = $this->resolveZoneForShipment($shipment);
            if (!$resolvedZone) {
                return sendResponse("", [], false, ["This shipment doesn't have any zone."], 500);
            }

            $zoneInfo = $sorterService->determineParcelZone($shipment, $resolvedZone);

            $zone = $zoneInfo['zone'];
            $action = $zoneInfo['action'];
            $area = $zoneInfo['area'];

            // This endpoint only returns information without modifying the shipment
            // No history is created, no status is changed

            return sendResponse("Shipment routing information retrieved.", new SorterResource(['shipment' => $shipment, 'action' => $action, 'area' => $area]));
        } catch (Exception $e) {
            return sendResponse("An error occurred while retrieving warehouse sort information.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * Handle OFD (Out For Delivery) parcels.
     * this sort deals with only shipments with exceptions.
     * @param Request $request Requires 'tracking_no'
     * @param OFDService $ofd Auto-injected service
     * @return \Illuminate\Http\JsonResponse
     *   - 200: Exception resolution details
     *   - 500: No exception/processing errors
     * Handles multiple exception types:
     * - Future delivery scheduling
     * - Wrong location reassignment
     * - OFD attempt limits
     * - CRM escalation
     * Updates driver assignments and runsheet status
     */
    public function sort_ofd(Request $request, OFDService $ofd)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('Sorter')) {
            return sendResponse(
                "Unauthorized: Only sorters can perform this action.",
                [],
                false,
                ["You do not have permission to perform this action."],
                403
            );
        }
        $request->validate([
            'tracking_no' => 'required|exists:shipments,tracking_no',
        ]);

        $trackingNo = trim($request->tracking_no);

        DB::beginTransaction();

        try {
            $shipment = Shipment::withoutGlobalScope(\App\Models\Scopes\ExcludeReturnShipmentsScope::class)
                ->where('tracking_no', $trackingNo)
                ->firstOrFail();

            // dd(strtolower($shipment->coreStatus()->name));

            if (strtolower($shipment->coreStatus()->name) == "delivered") {
                return sendResponse("Shipment is already delivered.", new SorterResource(["tracking_no" => $shipment->tracking_no, "action" => "MOVE_TO_SUPERVISOR",]));
            }

            $isReturnShipment = (bool) $shipment->is_return;

            if (!$shipment->in_exception && !$isReturnShipment) {
                return sendResponse("This Shipment has no exception.", [], false, ["This Shipment has no exception"], 500);
            }

            if ($isReturnShipment && !$shipment->in_exception) {
                $zone = $this->resolveZoneForShipment($shipment);
                if (!$zone) {
                    return sendResponse("", [], false, ["This shipment doesn't have any zone."], 500);
                }

                $zone = Zone::find($zone->id);
                $zoneOwner = $zone?->owner;
                $userOwner = $user->owner;

                if (!$zone || !$zoneOwner || !$userOwner) {
                    return sendResponse("", [], false, ["There is a problem with zone or user owner."], 422);
                }

                $shipmentInfo = $shipment->shipment_information;
                if (!$shipmentInfo) {
                    $shipmentInfo = $shipment->shipment_information()->create([
                        'shipment_id' => $shipment->id,
                        'tracking_no' => $shipment->tracking_no,
                        'zone_id' => null,
                        'in_warehouse' => false,
                    ]);
                }

                $shipmentInfo->zone_id = $zone->id;
                $shipmentInfo->in_warehouse = true;
                $shipmentInfo->save();

                if ($zoneOwner->is($userOwner)) {
                    $status = ShipmentStatusEnum::MOVE_TO_DISPATCH;
                    ZoneShipment::firstOrCreate([
                        'zone_id' => $zone->id,
                        'shipment_tracking_no' => $shipment->tracking_no,
                    ]);
                    $description = "Return shipment ready for merchant delivery: Zone - {$zone->name}";
                } else {
                    $status = ShipmentStatusEnum::MOVE_TO_AREA;
                    TransferShipment::firstOrCreate([
                        'owner_id' => $zone->owner_id,
                        'owner_type' => $zone->owner_type,
                        'shipment_tracking_no' => $shipment->tracking_no,
                    ]);
                    $description = "Return shipment belongs to {$zoneOwner->name}";
                }

                $shipment->owner_type = facility("type");
                $shipment->owner_id = facility("id");
                $shipment->in_exception = false;
                $shipment->is_sorted = true;
                $shipment->save();

                shipmentHistory([
                    "shipment_id" => $shipment->id,
                    "status" => status(ShipmentStatusEnum::ORDER_SORTED)['label'],
                    "description" => $description,
                    "type" => "RETURN_SHIPMENT",
                ]);

                updateShipmentStatus($shipment->id, status(ShipmentStatusEnum::ORDER_SORTED)['label']);

                DriverRunsheetShipment::where("shipment_tracking_no", $shipment->tracking_no)->update([
                    "status" => "returned"
                ]);

                DriverShipmentAssignment::where('shipment_id', $shipment->id)->update([
                    'returned_at' => operation_now(),
                    'status' => 'RETURNED',
                ]);
                DriverShipmentAssignment::where('shipment_id', $shipment->id)->delete();

                DB::commit();

                return sendResponse("Shipment Sorted.", new SorterResource([
                    "tracking_no" => $shipment->tracking_no,
                    "action" => $status,
                    "description" => $description,
                    'delivery_exception' => null,
                    'ofd_remaining_shipments' => [],
                    'ofd_times' => optional($shipment->shipment_delivery)->ofd_times,
                    'future_delivery_date' => null
                ]));
            }

            $zone = $isReturnShipment
                ? $this->resolveZoneForShipment($shipment)
                : $shipment->zone();
            if (!$zone) {
                return sendResponse("", [], false, ["This shipment is not an OFD."], 500);
            }

            $latestException = $shipment->core_exception;
            $delivery_exception = $latestException ? $latestException->type : null;
            $future_delivery_date = null;

            $assignment = DriverShipmentAssignment::where('shipment_id', $shipment->id)->first();
            $ofdRemainingShipments = $ofd->remainingOFDShipments($assignment->driver);

            $status = ShipmentStatusEnum::DELIVERY_EXCEPTION;
            $description = "Sorting action for delivery exception [$delivery_exception]";
            $deliveryException = DeliveryException::where('name', $delivery_exception)->first();
            $ofdCount = (int) optional($shipment->shipment_delivery)->ofd_count;
            $ofdLimit = (int) (optional(shipper($shipment)->setting)->failed_ofd_count ?? 0);
            $rawThreshold = setting('no_answer_rto_threshold');
            $noAnswerRtoThreshold = (is_numeric($rawThreshold) ? (int) $rawThreshold : 4);
            if ($noAnswerRtoThreshold < 4) {
                $noAnswerRtoThreshold = 4;
            }

            $noAnswerCount = ShipmentHistory::where('shipment_id', $shipment->id)
                ->where('type', 'NO_ANSWER')
                ->whereIn('name', ['MOVE_TO_DISPATCH', 'DELIVERY_EXCEPTION'])
                ->count();
            if ($delivery_exception === 'FUTURE_DELIVERY') {
                $futureDate = Carbon::parse(optional($shipment->shipment_delivery)->future_delivery_date);
                $future_delivery_date = $futureDate;
                $status = ShipmentStatusEnum::MOVE_TO_SHELF;
                $description = "Shipment scheduled for " . $futureDate->toDateString();
            } elseif ($delivery_exception === 'CANCELLED') {
                $status = ShipmentStatusEnum::MOVE_TO_SHELF;
                $description = "Shipment has been cancelled and moved to shelf.";
            } elseif ($delivery_exception === 'RTO') {
                $status = ShipmentStatusEnum::RTO;
                $description = "RTO shipment flagged for return to origin.";
            } elseif ($delivery_exception === 'TOMORROW') {
                $status = ShipmentStatusEnum::MOVE_TO_DISPATCH;
                $description = "Shipment will be delivered tomorrow";
            } elseif ($delivery_exception === 'NO_ANSWER') {
                $nextAttempt = $noAnswerCount + 1;

                if ($nextAttempt >= $noAnswerRtoThreshold) {
                    $status = ShipmentStatusEnum::RTO;
                    $description = "NO_ANSWER reached {$nextAttempt} attempts. Moving to RTO.";

                    shipmentHistory([
                        "shipment_id" => $shipment->id,
                        "name" => "CRM_TASK",
                        "description" => "CRM task triggered: NO_ANSWER attempts reached threshold",
                        "type" => "CRM"
                    ]);
                } else {
                    $status = ShipmentStatusEnum::MOVE_TO_DISPATCH;
                    $description = "Customer did not answer (attempt {$nextAttempt}/{$noAnswerRtoThreshold}), retrying delivery.";
                }
            } else {

                $noAnswerCount = ShipmentHistory::where('shipment_id', $shipment->id)
                    ->where('type', 'NO_ANSWER')
                    ->count();
                $nextAttempt = $noAnswerCount + 1;
                if ($delivery_exception === 'NO_ANSWER') {
                    if ($nextAttempt >= $noAnswerRtoThreshold) {
                        $status = ShipmentStatusEnum::RTO;
                        $description = "NO_ANSWER reached {$nextAttempt} attempts. Moving to RTO.";

                        shipmentHistory([
                            "shipment_id" => $shipment->id,
                            "status" => "CRM_TASK",
                            "description" => "CRM task triggered: NO_ANSWER attempts reached threshold",
                            "type" => "CRM"
                        ]);
                    } else {
                        $status = ShipmentStatusEnum::MOVE_TO_DISPATCH;
                        $description = "Customer did not answer (attempt {$nextAttempt}/{$noAnswerRtoThreshold}), retrying delivery.";
                    }
                } elseif ($ofdLimit > 0 && $ofdCount > $ofdLimit) {
                    $status = ShipmentStatusEnum::MOVE_TO_SHELF;
                    $description = "OFD attempts exceeded limit ({$ofdLimit}), moving shipment to CRM.";

                    CrmTask::create([
                        'title' => "Shipment exceeded OFD limit ({$ofdLimit}) and requires CRM attention: {$delivery_exception}",
                        'shipment_id' => $shipment->id,
                        'status' => 'created',
                        'owner_id' => $shipment->owner_id,
                        'owner_type' => $shipment->owner_type,
                    ]);

                    shipmentHistory([
                        "shipment_id" => $shipment->id,
                        "status" => "CRM_TASK",
                        "description" => "CRM task has been created due to OFD attempts finished",
                        "type" => $delivery_exception
                    ]);
                } else {
                    switch ($delivery_exception) {
                        case 'WRONG_CITY':
                        case 'WRONG_DISTRICT':
                            $status = ShipmentStatusEnum::MOVE_TO_SUPERVISOR;
                            $description = "Shipment is in the wrong location and requires reassignment.";
                            break;

                        case 'NO_ANSWER':
                            $status = ShipmentStatusEnum::MOVE_TO_DISPATCH;
                            $description = "Customer did not answer, retrying delivery.";
                            break;

                        default:
                            if ($deliveryException && $deliveryException->move_to_crm) {
                                $status = ShipmentStatusEnum::CRM_STARTED;
                                $description = "CRM flow started per exception setup.";
                            }
                            break;
                    }
                }
            }


            if ($status === "CRM_STARTED" || $status === "MOVE_TO_SUPERVISOR") {
                DriverShipmentAssignment::where('shipment_id', $shipment->id)->update(['returned_at' => operation_now()]);
                $shipment->in_exception = true;
                $shipment->save();
            }

            if ($shipment->shipment_information) {
                $shipment->shipment_information->in_warehouse = true;
                $shipment->shipment_information->save();
            }

            $shipment->owner_type = facility("type");
            $shipment->owner_id = facility("id");

            if ($delivery_exception === 'WRONG_CITY') {
                $shipment->in_exception = true;
            } else {
                $shipment->in_exception = false;
            }

            $shipment->is_sorted = true;
            $shipment->save();

            shipmentHistory([
                "shipment_id" => $shipment->id,
                "status" => $status,
                "description" => $description,
                "type" => $delivery_exception
            ]);

            updateShipmentStatus($shipment->id, status($status)['label']);

            DriverRunsheetShipment::where("shipment_tracking_no", $shipment->tracking_no)->update([
                "status" => "returned"
            ]);
            DriverShipmentAssignment::where('shipment_id', $shipment->id)->delete();

            DB::commit();


            return sendResponse("Shipment Sorted.", new SorterResource([
                "tracking_no" => $shipment->tracking_no,
                "action" => $status,
                "description" => $description,
                'delivery_exception' => $delivery_exception,
                'ofd_remaining_shipments' => $ofdRemainingShipments,
                'ofd_times' => $shipment->shipment_delivery->ofd_times,
                'future_delivery_date' => $future_delivery_date
            ]));
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("An error occurred while processing the inbound sort.", [], false, [$e->getMessage()], 500);
        }
    }


    // public function sort_ofd(Request $request, OFDService $ofd)
    // {
    //     $request->validate([
    //         'tracking_no' => 'required|exists:shipments,tracking_no',
    //     ]);

    //     $trackingNo = trim($request->tracking_no);

    //     DB::beginTransaction();

    //     $shipment = Shipment::where('tracking_no', $trackingNo)->firstOrFail();

    //     if ($shipment->in_exception) {
    //         try {
    //             $shipment = Shipment::where('tracking_no', $trackingNo)->firstOrFail();

    //             $zone = $shipment->consignee->zone();

    //             if (!$zone) {
    //                 return sendResponse("", [], false, ["This shipment is not an OFD."], 500);
    //             }

    //             $zone = Zone::find($zone->id);
    //             $assignment = DriverShipmentAssignment::where('shipment_id', $shipment->id)->first();
    //             $core_status = $shipment->coreStatus()->name;
    //             $options = setting_select_options();

    //             $delivery_exception = $shipment->shipmentHistories->first()->type;
    //             $ofdRemainingShipments = $ofd->remainingOFDShipments($assignment->driver);
    //             $match = null;


    //             DB::commit();
    //             return sendResponse("Shipment Sorted.", new SorterResource([
    //                 "tracking_no" => $shipment->tracking_no,
    //                 "action" => $match,
    //                 'delivery_exception' => $delivery_exception,
    //                 'ofd_remaining_shipments' => $ofdRemainingShipments,
    //                 'ofd_times' => $shipment->shipment_delivery->ofd_times
    //             ]));
    //         } catch (Exception $e) {
    //             DB::rollBack();
    //             return sendResponse("An error occurred while processing the inbound sort.", [], false, [$e->getMessage()], 500);
    //         }
    //     } else {
    //         return sendResponse("This Shipment has no exception.", [], false, ["This Shipment has no exception"], 500);
    //     }
    // }

    /**
     * This function is used for only pickup shipments from merchant.
     * this is part of Pickup Tasks.
     * @param Request $request Requires 'tracking_no'
     * @return \Illuminate\Http\JsonResponse
     *   - 200: Pickup completion details
     *   - 404: Missing pickup record
     *   - 422: Zone issues
     *   - 500: Processing errors
     * Updates pickup task status
     * Creates PICKUP_COMPLETED and INBOUND histories
     * Performs final warehouse sorting
     */
    public function pickup_sort(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('Sorter')) {
            return sendResponse(
                "Unauthorized: Only sorters can perform this action.",
                [],
                false,
                ["You do not have permission to perform this action."],
                403
            );
        }
        $validated = $request->validate([
            'tracking_no' => 'required|string|exists:shipments,tracking_no',
        ]);

        $trackingNo = trim($validated['tracking_no']);

        DB::beginTransaction();
        try {
            $shipment = Shipment::where('tracking_no', $trackingNo)->firstOrFail();

            if (strtolower($shipment->coreStatus()->name) == "delivered") {
                return sendResponse("Shipment is already delivered.", new SorterResource(["tracking_no" => $shipment->tracking_no, "action" => "MOVE_TO_SUPERVISOR",]));
            }

            if ($shipment->in_exception) {
                return sendResponse("This Shipment has no exception.", [], false, ["This Shipment has an exception please use sort ofd function"], 500);
            }

            $pickupShipment = MerchantPickupShipment::where('shipment_tracking_no', $trackingNo)->first();
            if (!$pickupShipment) {
                return sendResponse("Pickup shipment not found.", [], false, ["No pickup shipment record exists for this tracking number."], 404);
            }

            $pickupShipment->status = 'pickup_completed';
            $pickupShipment->save();

            $pickupTask = MerchantPickupTask::find($pickupShipment->pickup_task_id);
            if ($pickupTask) {
                $remainingShipments = MerchantPickupShipment::where('pickup_task_id', $pickupTask->id)
                    ->where('status', '!=', 'pickup_completed')
                    ->count();

                if ($remainingShipments === 0) {
                    $pickupTask->status = 'pickup_completed';
                    $pickupTask->save();
                }
            }

            $zone = $shipment->consignee->zone();
            if (!$zone) {
                return sendResponse("No zone found for shipment.", [], false, ["This shipment does not have an associated zone."], 422);
            }
            $zone = Zone::find($zone->id);

            $zoneOwner = $zone->owner;
            $userOwner = Auth::user()->owner;
            if (!$zoneOwner || !$userOwner) {
                return sendResponse("Zone or user owner error.", [], false, ["There is a problem with zone or user owner."], 422);
            }

            $pickup_completed_status = "PICKUP_COMPLETED";
            shipmentHistory([
                "status" => status($pickup_completed_status)['name'],
                "description" => status($pickup_completed_status)['description'],
                "shipment_id" => $shipment->id,
            ]);

            $inboundStatus = "ORDER_INBOUNDED";
            shipmentHistory([
                "status" => status($inboundStatus)['label'],
                "description" => status($inboundStatus)['description'],
                "shipment_id" => $shipment->id,
            ]);

            if ($zoneOwner->is($userOwner)) {
                $action = ShipmentStatusEnum::MOVE_TO_DISPATCH;
                ZoneShipment::firstOrCreate([
                    'zone_id' => $zone->id,
                    'shipment_tracking_no' => $shipment->tracking_no,
                ]);
                $area = $zone->name;
                $sort_description = "Move to Dispatch: Zone - " . $zone->name;
            } else {
                $action = ShipmentStatusEnum::MOVE_TO_AREA;
                TransferShipment::firstOrCreate([
                    'owner_id' => $zone->owner_id,
                    'owner_type' => $zone->owner_type,
                    'shipment_tracking_no' => $shipment->tracking_no,
                ]);
                $area = $zone->owner->name;
                $sort_description = "This shipment belongs to " . $zone->owner->name;
            }

            if ($shipment->shipment_information) {
                $shipment->shipment_information->in_warehouse = true;
                $shipment->shipment_information->save();
            }

            $shipment->owner_type = facility("type");
            $shipment->owner_id = facility("id");
            $shipment->is_sorted = true;
            $shipment->save();

            $sortStatus = ShipmentStatusEnum::ORDER_SORTED;
            shipmentHistory([
                "status" => status($sortStatus)['label'],
                "description" => $sort_description,
                "shipment_id" => $shipment->id,
            ]);

            updateShipmentStatus($shipment->id, status($sortStatus)['label']);

            DB::commit();
            return sendResponse("Pickup shipment sorted successfully.", new SorterResource(['shipment' => $shipment->tracking_no, 'action' => $action, 'area' => $area]), true, [], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("An error occurred while sorting the pickup shipment.", [], false, [$e->getMessage()], 500);
        }
    }

    public function stockout(Request $request, ParcelShelfService $parcelShelf, SorterService $sorterService)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('Sorter')) {
            return sendResponse(
                "Unauthorized: Only sorters can perform this action.",
                [],
                false,
                ["You do not have permission to perform this action."],
                403
            );
        }
        $request->validate([
            'tracking_no' => 'required|exists:shipments,tracking_no',
            'stock_out_task_id' => 'required|exists:stock_out_tasks,id',
        ]);

        DB::beginTransaction();
        try {

            $trackingNo = trim($request->tracking_no);

            $shipment = Shipment::where('tracking_no', $trackingNo)->firstOrFail();

            $soto = StockOutTaskShipment::where('stock_out_task_id', $request->stock_out_task_id)
                ->where('shipment_tracking_no', $request->tracking_no)
                ->where('status', 'pending')
                ->first();

            if (!$soto) {
                return sendResponse(
                    'not part of a Stock out task.',
                    [],
                    false,
                    ['not part of a Stock out task.']
                );
            }

            $sorterService->disallowDeliveredParcels($shipment);

            if (!$parcelShelf->isOnShelf($shipment->tracking_no)) {
                return sendResponse(
                    'Parcel not on shelf.',
                    [],
                    false,
                    ['This parcel is not assigned to any shelf and cannot be stocked out.']
                );
            }

            $parcelShelf->removeFromShelf($shipment->tracking_no);

            $latestHistory = $shipment->shipmentHistories->first();
            $exceptionType = $latestHistory ? $latestHistory->type : null;

            if ($exceptionType === 'FUTURE_DELIVERY') {
                $futureDate = Carbon::parse($shipment->shipment_delivery->future_delivery_date);
                if ($futureDate->isToday()) {
                    $zone = Zone::find(
                        $shipment->consignee->zone()->id
                    );
                    return sendResponse(
                        'Parcel stocked out for today’s delivery.',
                        new SorterResource([
                            'tracking_no' => $shipment->tracking_no,
                            'action' => 'MOVE_TO_DISPATCH',
                            'area' => $zone->name,
                        ])
                    );
                }
            }

            $sortStatus = ShipmentStatusEnum::STOCKOUT;

            shipmentHistory([
                "status" => $sortStatus,
                "description" => "This shipment is taken from the shelf.",
                "shipment_id" => $shipment->id,
            ]);

            $pendingCount = StockOutTaskShipment::where('stock_out_task_id', $request->stock_out_task_id)
                ->where('status', 'pending')
                ->count();

            $task = StockOutTask::find($request->stock_out_task_id);
            if ($pendingCount === 0) {
                $task->update([
                    'status' => 'completed'
                ]);
            } else {
                if ($task->status != "in_progress") {
                    StockOutTask::find($request->stock_out_task_id)->update([
                        'status' => 'in_progress'
                    ]);
                }
            }

            updateShipmentStatus($shipment->id, $sortStatus);

            if ($exceptionType === 'CANCELLED') {
                return sendResponse(
                    'Shipment has been cancelled; CRM will contact the customer.',
                    [],
                    true,
                    ['This shipment is cancelled and cannot be stocked out.']
                );
            }

            DB::commit();

            return sendResponse(
                'Shipment has been Removed from shelf',
                [],
                true,
                ['Shipment has been Removed from shelf.']
            );
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("An error occurred while stocking out the parcel.", [], false, [$e->getMessage()], 500);
        }
    }
}
