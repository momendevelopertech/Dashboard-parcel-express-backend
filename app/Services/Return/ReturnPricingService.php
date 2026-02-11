<?php

namespace App\Services\Return;

use App\Models\MerchantCommission;
use App\Models\DriverCommission;

/**
 * ReturnPricingService
 * 
 * Handles pricing calculations for return shipments.
 * Renamed from ReversePickupPricingService for clarity.
 * 
 * No major logic changes, just cleaner naming.
 */
class ReturnPricingService
{
    /**
     * Calculate return fee for a return shipment
     * 
     * Based on merchant commission table.
     * 
     * @param int $merchantId
     * @param int $stateId Customer's state (pickup location)
     * @return array {base_return: float, return_discount: float, final_return_fee: float}
     */
    public function calculateReturnFee(int $merchantId, int $stateId): array
    {
        $commission = MerchantCommission::where('merchant_id', $merchantId)
            ->where('state_id', $stateId)
            ->first();


        if (!$commission) {
            // Fallback to default pricing
            return [
                'base_return' => 1.500,
                'return_discount' => 0.000,
                'final_return_fee' => 1.500,
            ];
        }

        $baseReturn = (float) $commission->base_return_fee;
        $returnDiscount = (float) $commission->return_discount_amount;
        $finalReturnFee = (float) $commission->return_fee;

        // Calculate if not set
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
     * Calculate driver commission for return pickup
     * 
     * Lower than delivery commission (no COD handling risk).
     * 
     * @param int $driverId
     * @param int|null $stateId Pickup state
     * @return float Commission amount
     */
    public function calculateDriverCommission(int $driverId, ?int $stateId): float
    {
        if (empty($stateId)) {
            // No state context available, use safe default.
            return 0.400;
        }

        $commission = DriverCommission::where('driver_id', $driverId)
            ->where('state_id', $stateId)
            ->first();

        if (!$commission) {
            // Fallback to default
            return 0.400;
        }

        // Use pickup_fee for returns
        $pickupFee = (float) $commission->pickup_fee;

        return $pickupFee > 0 ? $pickupFee : 0.400;
    }

    /**
     * Calculate company revenue
     * 
     * @param float $merchantFee Fee charged to merchant
     * @param float $driverCommission Commission paid to driver
     * @return float Company revenue
     */
    public function calculateCompanyRevenue(float $merchantFee, float $driverCommission): float
    {
        return max(0, $merchantFee - $driverCommission);
    }

    /**
     * Get complete pricing breakdown
     * 
     * @param int $merchantId
     * @param int $driverId
     * @param int $stateId
     * @return array Complete breakdown
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
