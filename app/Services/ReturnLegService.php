<?php

namespace App\Services;

use App\Enums\ShipmentStatusEnum;
use App\Models\CommissionTemplate;
use App\Models\Merchant;
use App\Models\MerchantCommission;
use App\Models\Partner;
use App\Models\ReverseShipment;
use App\Models\Shipment;
use App\Models\ShipmentDelivery;
use App\Models\ShipmentFinance;
use App\Models\ShipmentInformation;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ReturnLegService
{
    /**
     * Create a return leg shipment from a reverse shipment.
     * Idempotent: returns existing return leg if already created.
     * Optional note/proof is stored on intake history.
     */
    public function createFromReverseShipment(ReverseShipment $reverseShipment, ?User $actor = null, ?string $note = null, ?string $proofPath = null): Shipment
    {
        $existing = Shipment::where('parent_reverse_shipment_id', $reverseShipment->id)
            ->orWhere('tracking_no', $reverseShipment->tracking_no)
            ->first();

        if ($existing) {
            if (($existing->direction ?? null) !== 'return_to_origin') {
                [$returnToType, $returnToId] = $this->resolveReturnDestination($reverseShipment, $reverseShipment->parentShipment);
                $existing->direction = 'return_to_origin';
                $existing->return_kind = 'reverse_pickup';
                $existing->return_to_type = $existing->return_to_type ?: $returnToType;
                $existing->return_to_id = $existing->return_to_id ?: $returnToId;
                $existing->is_return = true;
                $existing->parent_reverse_shipment_id = $reverseShipment->id;
                $existing->save();
            }

            $original = $reverseShipment->parentShipment;
            [$returnToType, $returnToId] = $this->resolveReturnDestination($reverseShipment, $original);
            $merchantId = $original?->merchant_id ?? $reverseShipment->merchant_id;

            $shouldReprice = ($existing->return_fee_source ?? null) === 'reverse_pickup'
                || (float) ($existing->return_fee ?? 0) <= 0;

            if ($shouldReprice) {
                $returnFees = $this->calculateReturnFees($merchantId, $reverseShipment, $original, 'reverse_pickup', $returnToType);
                $existing->return_to_type = $existing->return_to_type ?: $returnToType;
                $existing->return_to_id = $existing->return_to_id ?: $returnToId;
                $existing->return_fee_before_discount = $returnFees['base'] ?? 0;
                $existing->return_fee_discount = $returnFees['discount'] ?? 0;
                $existing->return_fee = $returnFees['final'] ?? 0;
                $existing->return_fee_source = $returnFees['source'] ?? $existing->return_fee_source;
                $existing->save();
            }
            return $existing;
        }

        $original = $reverseShipment->parentShipment;

        [$returnToType, $returnToId] = $this->resolveReturnDestination($reverseShipment, $original);

        $merchantId = $original?->merchant_id ?? $reverseShipment->merchant_id;
        $shipperId = $original?->shipper_id ?? null;
        $marketplacePartnerId = $original?->marketplace_partner_id ?? null;

        $returnFees = $this->calculateReturnFees($merchantId, $reverseShipment, $original, 'reverse_pickup', $returnToType);

        $returnName = $this->resolveReturnName($returnToType, $returnToId, $merchantId, $original);
        $returnPhone = $this->resolveReturnPhone($returnToType, $returnToId, $merchantId, $original);

        $receiverAddressId = $reverseShipment->receiver_address_id;
        $deliveryAddressId = $reverseShipment->receiver_address_id;

        $facility = facility();
        if (!$facility && $reverseShipment->reversePickupRequest) {
            $facility = (object) [
                'id' => $reverseShipment->reversePickupRequest->owner_id,
                'type' => $reverseShipment->reversePickupRequest->owner_type,
            ];
        }
        if (!$facility && $actor && $actor->owner_id && $actor->owner_type) {
            $facility = (object) [
                'id' => $actor->owner_id,
                'type' => $actor->owner_type,
            ];
        }
        $shipment = Shipment::create([
            'shipper_id' => $shipperId,
            'merchant_id' => $merchantId,
            'marketplace_partner_id' => $marketplacePartnerId,
            'value' => 0,
            'notes' => 'Return leg created from reverse shipment',
            'receiver_id' => $returnToType === 'merchant' ? $returnToId : null,
            'consignee_id' => $reverseShipment->consignee_id,
            'driver_id' => null,
            'receiver_address_id' => $receiverAddressId,
            'sender_id' => $reverseShipment->sender_id,
            'sender_address_id' => $reverseShipment->sender_address_id,
            'latitude' => $reverseShipment->latitude,
            'longitude' => $reverseShipment->longitude,
            'location_url' => $reverseShipment->location_url,
            'delivery_address_id' => $deliveryAddressId,
            'customer_name' => $returnName,
            'customer_phone' => $returnPhone,
            'streetAddress' => $reverseShipment->receiverAddress?->streetAddress,
            'payment_type' => 'PAID',
            'fee_payer' => 'merchant',
            'tracking_no' => $reverseShipment->tracking_no,
            'total_cod' => 0,
            'delivery_fee' => 0,
            'shipment_type_id' => 1,
            'status' => ShipmentStatusEnum::ORDER_SORTED,
            'direction' => 'return_to_origin',
            'return_kind' => 'reverse_pickup',
            'return_to_type' => $returnToType,
            'return_to_id' => $returnToId,
            'is_return' => true,
            'return_fee_before_discount' => $returnFees['base'] ?? 0,
            'return_fee_discount' => $returnFees['discount'] ?? 0,
            'return_fee' => $returnFees['final'] ?? 0,
            'return_fee_source' => $returnFees['source'] ?? null,
            'parent_reverse_shipment_id' => $reverseShipment->id,
            'owner_type' => $facility?->type,
            'owner_id' => $facility?->id,
            'facility_type' => $facility?->type,
            'facility_id' => $facility?->id,
        ]);

        ShipmentInformation::create([
            'shipment_id' => $shipment->id,
            'tracking_no' => $shipment->tracking_no,
            'zone_id' => null,
            'in_warehouse' => true,
            'status' => null,
        ]);

        ShipmentDelivery::create([
            'shipment_id' => $shipment->id,
        ]);

        ShipmentFinance::create([
            'shipment_tracking_no' => $shipment->tracking_no,
        ]);

        // History: Inbounded + Sorted for return leg
        $inboundStatus = ShipmentStatusEnum::ORDER_INBOUNDED;
        $inboundDescription = 'Return leg received at hub';
        if ($note) {
            $inboundDescription .= ' - Note: ' . $note;
        }

        shipmentHistory([
            'status' => status($inboundStatus)['label'],
            'description' => $inboundDescription,
            'shipment_id' => $shipment->id,
            'proof' => $proofPath,
        ]);

        $sortedStatus = ShipmentStatusEnum::ORDER_SORTED;
        shipmentHistory([
            'status' => status($sortedStatus)['label'],
            'description' => 'Return leg ready for return dispatch',
            'shipment_id' => $shipment->id,
        ]);

        $shipment->is_sorted = true;
        $shipment->save();

        Log::info('Return leg created', [
            'reverse_shipment_id' => $reverseShipment->id,
            'shipment_id' => $shipment->id,
            'tracking_no' => $shipment->tracking_no,
        ]);

        return $shipment;
    }

    private function resolveReturnDestination(ReverseShipment $reverseShipment, ?Shipment $original): array
    {
        if ($original && $original->marketplace_partner_id) {
            return ['marketplace_partner', $original->marketplace_partner_id];
        }

        if ($original && $original->merchant_id) {
            return ['merchant', $original->merchant_id];
        }

        if ($reverseShipment->merchant_id) {
            return ['merchant', $reverseShipment->merchant_id];
        }

        throw new \Exception('Return destination not found for reverse shipment');
    }

    private function calculateReturnFees(?int $merchantId, ReverseShipment $reverseShipment, ?Shipment $original, string $returnKind, string $returnToType): array
    {
        // For reverse pickup, we now apply return fees on the shipment return leg (avoid double-charging elsewhere).

        $stateId = $original?->pickupAddress?->state_id
            ?? $reverseShipment->receiverAddress?->state_id
            ?? $original?->deliveryAddress?->state_id;
        $countryId = $original?->pickupAddress?->country_id
            ?? $reverseShipment->receiverAddress?->country_id
            ?? $original?->deliveryAddress?->country_id;

        if ($merchantId && (!$stateId || !$countryId)) {
            $merchantProfile = Merchant::where('user_id', $merchantId)->first();
            if (!$stateId) {
                $stateId = $merchantProfile?->state_id;
            }
            if (!$countryId) {
                $countryId = $merchantProfile?->country_id;
            }
        }

        if ($merchantId) {
            $commission = null;
            if ($stateId) {
                $commission = MerchantCommission::where('merchant_id', $merchantId)
                    ->where('state_id', $stateId)
                    ->first();
            }
            if (!$commission) {
                $commission = MerchantCommission::where('merchant_id', $merchantId)
                    ->whereNull('state_id')
                    ->first();
            }

            if ($commission) {
                $base = (float) ($commission->base_return_fee ?? 0);
                $discount = (float) ($commission->return_discount_amount ?? 0);
                $final = isset($commission->return_fee) ? (float) $commission->return_fee : max(0, $base - $discount);

                return ['base' => $base, 'discount' => $discount, 'final' => $final, 'source' => 'merchant_commission'];
            }
        }

        $templateQuery = CommissionTemplate::query();
        $templateRow = null;
        if (!is_null($countryId)) {
            $templateRow = (clone $templateQuery)
                ->where('country_id', $countryId)
                ->where('state_id', $stateId)
                ->first();

            if (!$templateRow) {
                $templateRow = (clone $templateQuery)
                    ->where('country_id', $countryId)
                    ->whereNull('state_id')
                    ->first();
            }

            if (!$templateRow) {
                $templateRow = CommissionTemplate::whereNull('country_id')
                    ->where('state_id', $stateId)
                    ->first()
                    ?: CommissionTemplate::whereNull('country_id')->whereNull('state_id')->first();
            }
        } else {
            $templateRow = CommissionTemplate::where('state_id', $stateId)->first()
                ?: CommissionTemplate::whereNull('state_id')->first();
        }

        if ($templateRow) {
            $base = (float) ($templateRow->base_return_fee ?? 0);
            $discount = (float) ($templateRow->return_discount_amount ?? 0);
            $final = isset($templateRow->return_fee) ? (float) $templateRow->return_fee : max(0, $base - $discount);
            $source = $returnToType === 'marketplace_partner' ? 'partner_contract' : 'shipper_default';

            return ['base' => $base, 'discount' => $discount, 'final' => $final, 'source' => $source];
        }

        // Marketplace partner default fallback (no merchant_id and no template found)
        if ($returnToType === 'marketplace_partner' && !$merchantId) {
            $defaultPartnerFee = (float) setting('default_marketplace_return_fee');
            if ($defaultPartnerFee <= 0) {
                $defaultPartnerFee = 1.0;
            }
            return [
                'base' => $defaultPartnerFee,
                'discount' => 0.0,
                'final' => $defaultPartnerFee,
                'source' => 'partner_contract',
            ];
        }

        return ['base' => 0.0, 'discount' => 0.0, 'final' => 0.0, 'source' => 'shipper_default'];
    }

    private function resolveReturnName(string $returnToType, int $returnToId, ?int $merchantId, ?Shipment $original): ?string
    {
        if ($returnToType === 'merchant') {
            return $original?->merchant?->name ?? User::find($returnToId)?->name;
        }

        if ($returnToType === 'marketplace_partner') {
            return Partner::find($returnToId)?->name;
        }

        return null;
    }

    private function resolveReturnPhone(string $returnToType, int $returnToId, ?int $merchantId, ?Shipment $original): ?string
    {
        if ($returnToType === 'merchant') {
            return $original?->merchant?->phone ?? User::find($returnToId)?->phone;
        }

        return null;
    }
}
