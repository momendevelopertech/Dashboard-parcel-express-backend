<?php

namespace App\Services\ReversePickup;

use App\Models\ReverseShipment;
use App\Models\ReversePickupTransaction;
use App\Models\DriverAccount;
use App\Models\MerchantAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReversePickupFinancialService
{
    /**
     * Credit driver wallet when pickup is completed
     * Step 3: Driver Execution - commission paid immediately
     *
     * @param int $reverseShipmentId
     * @return ReversePickupTransaction
     * @throws \Exception
     */
    public function creditDriverCommission(int $reverseShipmentId): ReversePickupTransaction
    {
        return DB::transaction(function () use ($reverseShipmentId) {
            $reverseShipment = ReverseShipment::findOrFail($reverseShipmentId);

            // Get driver from reverse pickup shipment
            $reversePickupShipment = $reverseShipment->reversePickupShipments()->where('status', 'picked')->first();

            if (!$reversePickupShipment || !$reversePickupShipment->driver_id) {
                throw new \Exception('No driver assigned to this reverse shipment');
            }

            $driverId = $reversePickupShipment->driver_id;
            $commissionAmount = (float) $reverseShipment->driver_commission;

            if ($commissionAmount <= 0) {
                throw new \Exception('Invalid driver commission amount');
            }

            // Credit driver account
            $driverAccount = DriverAccount::firstOrCreate(
                ['driver_id' => $driverId],
                ['balance' => 0]
            );

            $driverAccount->increment('balance', $commissionAmount);

            // Create transaction record
            $transaction = ReversePickupTransaction::create([
                'reverse_shipment_id' => $reverseShipment->id,
                'user_id' => $driverId,
                'user_type' => 'driver',
                'transaction_type' => 'driver_commission_credit',
                'amount' => $commissionAmount,
                'status' => 'completed',
                'executed_at' => now(),
                'reference_type' => get_class($reversePickupShipment),
                'reference_id' => $reversePickupShipment->id,
                'note' => "Driver commission for reverse pickup: {$reverseShipment->tracking_no}",
            ]);

            return $transaction;
        });
    }

    /**
     * Debit merchant wallet when shipment is returned
     * Step 5: Final Delivery - fee charged to merchant
     *
     * @param int $reverseShipmentId
     * @return ReversePickupTransaction
     * @throws \Exception
     */
    public function debitMerchantFee(int $reverseShipmentId): ReversePickupTransaction
    {
        return DB::transaction(function () use ($reverseShipmentId) {
            $reverseShipment = ReverseShipment::findOrFail($reverseShipmentId);

            // Check if already charged
            if ($reverseShipment->merchant_fee_charged) {
                throw new \Exception('Merchant has already been charged for this reverse shipment');
            }

            $merchantId = $reverseShipment->merchant_id;
            $pricingCalculated = $reverseShipment->pricing_calculated;

            if (!isset($pricingCalculated['final_return_fee'])) {
                throw new \Exception('Pricing not calculated for this reverse shipment');
            }

            $feeAmount = (float)  $pricingCalculated['final_return_fee'];

            if ($feeAmount <= 0) {
                throw new \Exception('Invalid merchant fee amount');
            }

            // Debit merchant account
            $merchantAccount = MerchantAccount::firstOrCreate(
                ['merchant_id' => $merchantId],
                ['balance' => 0]
            );

            $merchantAccount->decrement('balance', $feeAmount);

            // Create transaction record
            $transaction = ReversePickupTransaction::create([
                'reverse_shipment_id' => $reverseShipment->id,
                'user_id' => $merchantId,
                'user_type' => 'merchant',
                'transaction_type' => 'merchant_fee_debit',
                'amount' => $feeAmount,
                'status' => 'completed',
                'executed_at' => now(),
                'reference_type' => ReverseShipment::class,
                'reference_id' => $reverseShipment->id,
                'note' => "Return fee for reverse shipment: {$reverseShipment->tracking_no}",
            ]);

            // Calculate company revenue
            $driverCommission = (float) $reverseShipment->driver_commission;
            $companyRevenue = $feeAmount - $driverCommission;

            // Log company revenue (you may want to store this in a separate table)
            \Log::info("Reverse Pickup Revenue", [
                'reverse_shipment_id' => $reverseShipment->id,
                'tracking_no' => $reverseShipment->tracking_no,
                'merchant_fee' => $feeAmount,
                'driver_commission' => $driverCommission,
                'company_revenue' => $companyRevenue,
            ]);

            return $transaction;
        });
    }

    /**
     * Get financial summary for a reverse shipment
     *
     * @param int $reverseShipmentId
     * @return array
     */
    public function getFinancialSummary(int $reverseShipmentId): array
    {
        $reverseShipment = ReverseShipment::with('transactions')->findOrFail($reverseShipmentId);

        $driverTransaction = $reverseShipment->transactions()
            ->where('transaction_type', 'driver_commission_credit')
            ->first();

        $merchantTransaction = $reverseShipment->transactions()
            ->where('transaction_type', 'merchant_fee_debit')
            ->first();

        $driverCommission = $driverTransaction ? (float) $driverTransaction->amount : 0;
        $merchantFee = $merchantTransaction ? (float) $merchantTransaction->amount : 0;
        $companyRevenue = $merchantFee - $driverCommission;

        return [
            'reverse_shipment_id' => $reverseShipment->id,
            'tracking_no' => $reverseShipment->tracking_no,
            'pricing_calculated' => $reverseShipment->pricing_calculated,
            'driver_commission' => $driverCommission,
            'driver_commission_paid' => $driverTransaction ? $driverTransaction->executed_at : null,
            'merchant_fee' => $merchantFee,
            'merchant_fee_charged' => $merchantTransaction ? $merchantTransaction->executed_at : null,
            'company_revenue' => $companyRevenue,
            'merchant_fee_charged_flag' => $reverseShipment->merchant_fee_charged,
        ];
    }
}
