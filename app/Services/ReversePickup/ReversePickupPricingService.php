<?php

namespace App\Services\ReversePickup;

use App\Models\MerchantCommission;
use App\Models\DriverCommission;

class ReversePickupPricingService
{
    /**
     * Calculate return pricing for a reverse shipment based on merchant commission table
     *
     * @param int $merchantId
     * @param int $stateId Customer's wilaya/state (pickup location)
     * @return array {base_return: float, return_discount: float, final_return_fee: float}
     */
    public function calculateReturnFee(int $merchantId, int $stateId): array
    {
        $commission = MerchantCommission::where('merchant_id', $merchantId)
            ->where('state_id', $stateId)
            ->first();

        if (!$commission) {
            // Fallback to default pricing if no specific commission found
            return [
                'base_return' => 1.500,
                'return_discount' => 0.000,
                'final_return_fee' => 1.500,
            ];
        }

        $baseReturn = (float) $commission->base_return_fee;
        $returnDiscount = (float) $commission->return_discount_amount;
        $finalReturnFee = (float) $commission->return_fee;

        // If return_fee is not set, calculate it
        if ($finalReturnFee === 0.0) {
            $finalReturnFee = max(0, $baseReturn - $returnDiscount);
        }

        return [
            'base_return' => $baseReturn,
            'return_discount' => $returnDiscount,
            'final_return_fee' => $finalReturnFee,
        ];
    }

    /**
     * Calculate driver commission for reverse pickup
     * Lower than delivery commission (no COD handling risk)
     *
     * @param int $driverId
     * @param int $stateId Pickup state/wilaya
     * @return float Commission amount (e.g., 0.400 OMR)
     */
    public function calculateDriverCommission(int $driverId, int $stateId): float
    {
        $commission = DriverCommission::where('driver_id', $driverId)
            ->where('state_id', $stateId)
            ->first();

        if (!$commission) {
            // Fallback to default reverse pickup commission
            return 0.400;
        }

        // Use pickup_fee for reverse pickups (should be configured lower than delivery_fee)
        $pickupFee = (float) $commission->pickup_fee;

        return $pickupFee > 0 ? $pickupFee : 0.400;
    }

    /**
     * Calculate company revenue for a reverse shipment
     *
     * @param float $merchantFee Final fee charged to merchant
     * @param float $driverCommission Commission paid to driver
     * @return float Company revenue (merchant_fee - driver_commission)
     */
    public function calculateCompanyRevenue(float $merchantFee, float $driverCommission): float
    {
        return max(0, $merchantFee - $driverCommission);
    }

    /**
     * Get complete pricing breakdown for a reverse shipment
     *
     * @param int $merchantId
     * @param int $driverId
     * @param int $stateId
     * @return array Complete breakdown with all financial details
     */
    public function getCompletePricingBreakdown(int $merchantId, int $driverId, int $stateId): array
    {
        $returnFeeBreakdown = $this->calculateReturnFee($merchantId, $stateId);
        $driverCommission = $this->calculateDriverCommission($driverId, $stateId);
        $companyRevenue = $this->calculateCompanyRevenue(
            $returnFeeBreakdown['final_return_fee'],
            $driverCommission
        );

        return [
            'merchant_charges' => $returnFeeBreakdown,
            'driver_commission' => $driverCommission,
            'company_revenue' => $companyRevenue,
        ];
    }
}
