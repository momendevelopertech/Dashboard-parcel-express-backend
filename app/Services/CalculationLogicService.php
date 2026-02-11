<?php

namespace App\Services;

use App\Models\Shipment;
use Illuminate\Support\Facades\Log;

/**
 * Centralized service for all financial calculations in the application.
 *
 * This service maintains all calculation rules in one place to ensure consistency
 * across backend controllers, models, and frontend API calls.
 *
 * All calculations follow the rules defined in Finance_development_instructions.md
 */
class CalculationLogicService
{
    /**
     * Calculate the amount a driver should collect for a shipment.
     *
     * Rules (same for COD and PAID):
     * - Merchant pays fee: Driver collects goods value only
     * - Customer pays fee: Driver collects value + base_delivery_fee (without discount)
     * Note: For PAID shipments, value is always 0, so:
     *   - PAID + Merchant pays fee: Driver collects 0
     *   - PAID + Customer pays fee: Driver collects base_delivery_fee
     *
     * @param Shipment|object|null $shipment Shipment model instance or object with shipment properties
     * @return float Amount driver should collect (rounded to 3 decimal places)
     */
    public function getDriverCollectibleAmount($shipment): float
    {
        if (!$shipment) {
            return 0.0;
        }
        $s=Shipment::find($shipment->id);


        // Handle both model instances and plain objects (from DB queries)
        $paymentType = strtoupper((string) ($s->payment_type ?? ''));
        $feePayer = strtolower((string) ($s->fee_payer ?? 'customer'));
        $value = (float) ($s->value ?? 0);

        // COD and PAID use the same calculation logic based on fee_payer
        if ($paymentType === 'COD' || $paymentType === 'PAID') {
            if ($feePayer === 'merchant') {
                // Merchant pays fee → driver collects goods value only (for PAID, value is 0, so collects 0)
                return $value > 0 ? (round($value, 3)): 0.0;
            }
            if ($feePayer === 'customer') {
                // Customer pays fee → driver collects value + base_delivery_fee (for PAID, value is 0, so collects base_delivery_fee)
                $baseDeliveryFee = $s->delivery_fee_before_discount;
                return round($value + $baseDeliveryFee, 3);
            }
        }

        return 0.0;
    }

    /**
     * Calculate COD amount for merchant account.
     *
     * Rules:
     * - Only COD shipments contribute
     * - Uses value field (goods value only, not including delivery fees)
     *
     * @param Shipment|object|null $shipment Shipment model instance or object
     * @return float COD amount for merchant (rounded to 3 decimal places)
     */
    public function getMerchantCOD($shipment): float
    {
        if (!$shipment) {
            return 0.0;
        }

        $paymentType = strtoupper((string) ($shipment->payment_type ?? ''));

        if ($paymentType === 'COD') {
            $value = (float) ($shipment->value ?? 0);
            return round($value, 3);
        }

        return 0.0;
    }

    /**
     * Calculate total COD amount for a shipment.
     *
     * Rules (same for COD and PAID):
     * - Merchant pays fee: total_cod = value
     * - Customer pays fee: total_cod = value + delivery_fee_before_discount
     * Note: For PAID shipments, value is always 0, so:
     *   - PAID + Merchant pays fee: total_cod = 0
     *   - PAID + Customer pays fee: total_cod = delivery_fee_before_discount
     *
     * @param Shipment|object|null $shipment Shipment model instance or object
     * @return float Total COD amount (rounded to 3 decimal places)
     */
    public function getTotalCOD($shipment): float
    {
        if (!$shipment) {
            return 0.0;
        }

        $paymentType = strtoupper((string) ($shipment->payment_type ?? ''));
        $feePayer = strtolower((string) ($shipment->fee_payer ?? 'customer'));
        // Use delivery_fee_before_discount if available, otherwise fallback to delivery_fee
        $deliveryFee = (float) ($shipment->delivery_fee_before_discount ?? $shipment->delivery_fee ?? 0);
        $value = (float) ($shipment->value ?? 0);

        // COD and PAID use the same calculation logic based on fee_payer
        if ($paymentType === 'COD' || $paymentType === 'PAID') {
            if ($feePayer === 'merchant') {
                // Merchant pays fee → total_cod = value (for PAID, value is 0, so total_cod = 0)
                return round($value, 3);
            }
            if ($feePayer === 'customer') {
                // Customer pays fee → total_cod = value + delivery_fee_before_discount (for PAID, value is 0, so total_cod = delivery_fee_before_discount)
                return round($value + $deliveryFee, 3);
            }
        }

        return 0.0;
    }

    /**
     * Calculate the amount field for a shipment.
     *
     * Rules (same for COD and PAID):
     * - Customer pays fee: amount = value + delivery_fee
     * - Merchant pays fee: amount = value
     * Note: For PAID shipments, value is always 0, so:
     *   - PAID + Customer pays fee: amount = delivery_fee
     *   - PAID + Merchant pays fee: amount = 0
     *
     * @param Shipment|object|null $shipment Shipment model instance or object
     * @return float Amount to be collected (rounded to 3 decimal places)
     */
    public function getAmount($shipment): float
    {
        if (!$shipment) {
            return 0.0;
        }

        $paymentType = strtoupper((string) ($shipment->payment_type ?? ''));
        $feePayer = strtolower((string) ($shipment->fee_payer ?? 'customer'));
        $deliveryFee = (float) ($shipment->delivery_fee ?? 0);
        $value = (float) ($shipment->value ?? 0);

        // COD and PAID use the same calculation logic based on fee_payer
        if ($paymentType === 'COD' || $paymentType === 'PAID') {
            if ($feePayer === 'customer') {
                // Customer pays fee → amount = value + delivery_fee (for PAID, value is 0, so amount = delivery_fee)
                return round($value + $deliveryFee, 3);
            }
            if ($feePayer === 'merchant') {
                // Merchant pays fee → amount = value (for PAID, value is 0, so amount = 0)
                return round($value, 3);
            }
        }

        return 0.0;
    }

    /**
     * Get base delivery fee for a shipment (before discounts).
     *
     * Priority:
     * 1. base_delivery_fee (from accessor or merchant_commission_transaction)
     * 2. delivery_fee_before_discount
     * 3. delivery_fee (fallback)
     *
     * @param Shipment|object|null $shipment Shipment model instance or object
     * @return float Base delivery fee (rounded to 3 decimal places)
     */
    public function getBaseDeliveryFee($shipment): float
    {
        if (!$shipment) {
            return 0.0;
        }

        // Try to get base_delivery_fee (works if shipment is a model with accessor)
        // if (isset($shipment->base_delivery_fee) && $shipment->base_delivery_fee !== null) {
        //     return round((float) $shipment->base_delivery_fee, 3);
        // }

        // Fallback to delivery_fee_before_discount
        if (isset($shipment->delivery_fee_before_discount) && $shipment->delivery_fee_before_discount > 0) {
            Log::info("base for shipment ".$shipment->id." depends on delivery_fee_before_discount"." with value ".$shipment->delivery_fee_before_discount );
            return round((float) $shipment->delivery_fee_before_discount, 3);
        }


        // Final fallback to delivery_fee
        $deliveryFee = (float) ($shipment->delivery_fee ?? 0);
        
        // Log if we're using fallback for debugging
        if ($deliveryFee > 0) {
            /* \Log::info('[CalculationLogicService] Using delivery_fee as base_delivery_fee fallback', [
                'shipment_id' => $shipment->id ?? null,
                'tracking_no' => $shipment->tracking_no ?? null,
                'delivery_fee' => $deliveryFee,
                'merchant_id' => $shipment->merchant_id ?? null,
            ]); */
        }
        
        return round($deliveryFee, 3);
    }

    /**
     * Calculate delivery fee on merchant (when merchant pays the fee).
     *
     * @param Shipment|object|null $shipment Shipment model instance or object
     * @return float Delivery fee amount (rounded to 3 decimal places), 0 if customer pays
     */
    public function getDeliveryFeeOnMerchant($shipment): float
    {
        if (!$shipment) {
            return 0.0;
        }

        $feePayer = strtolower((string) ($shipment->fee_payer ?? 'customer'));

        if ($feePayer === 'merchant') {
            $deliveryFee = (float) ($shipment->delivery_fee ?? 0);
            return round($deliveryFee, 3);
        }

        return 0.0;
    }

    /**
     * Calculate fee credit (discount benefit when customer pays fee but there's a discount).
     *
     * Formula: fee_credit = base_fee - effective_fee (when customer pays)
     *
     * @param Shipment|object|null $shipment Shipment model instance or object
     * @return float Fee credit amount (rounded to 3 decimal places)
     */
    public function getFeeCredit($shipment): float
    {
        if (!$shipment) {
            return 0.0;
        }

        $feePayer = strtolower((string) ($shipment->fee_payer ?? 'customer'));

        if ($feePayer !== 'merchant') {
            $baseFee = $this->getBaseDeliveryFee($shipment);
            $effectiveFee = (float) ($shipment->delivery_fee ?? 0);
            $credit = max(0, $baseFee - $effectiveFee);
            return round($credit, 3);
        }

        return 0.0;
    }

    /**
     * Calculate total collectible amount for multiple shipments.
     *
     * @param iterable $shipments Collection of shipments
     * @return float Total collectible amount (rounded to 3 decimal places)
     */
    public function getTotalCollectibleAmount(iterable $shipments): float
    {
        $total = 0.0;
        foreach ($shipments as $shipment) {
            $total += $this->getDriverCollectibleAmount($shipment);
        }
        return round($total, 3);
    }

    /**
     * Calculate total COD for merchant from multiple shipments.
     *
     * @param iterable $shipments Collection of shipments
     * @return float Total COD for merchant (rounded to 3 decimal places)
     */
    public function getTotalMerchantCOD(iterable $shipments): float
    {
        $total = 0.0;
        foreach ($shipments as $shipment) {
            $total += $this->getMerchantCOD($shipment);
        }
        return round($total, 3);
    }
}

