<?php

namespace App\Services;

use App\Models\MerchantCommission;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class MerchantCommissionService
{
    public function upsertForMerchant(int $merchantId, array $payload = []): MerchantCommission
    {
        // السماح بإرسال country/state للـ override، وإلا تبقى Global
        $key = [
            'merchant_id' => $merchantId,
            'country_id' => $payload['country_id'] ?? null,
            'state_id' => $payload['state_id'] ?? null,
        ];

        $defaults = config('commissions.defaults', []);

        // دمج الافتراضي مع المبعوت (المبعوت يغلب)
        $data = array_replace($defaults, Arr::only($payload, [
            'base_delivery_fee',
            'base_return_fee',
            'delivery_discount_amount',
            'return_discount_amount',
            'delivery_fee',
            'return_fee',
        ]));

        // احسب النهائي لو مش مبعوت
        $data['delivery_fee'] = $data['delivery_fee']
            ?? max(0, ($data['base_delivery_fee'] ?? 0) - ($data['delivery_discount_amount'] ?? 0));

        $data['return_fee'] = $data['return_fee']
            ?? max(0, ($data['base_return_fee'] ?? 0) - ($data['return_discount_amount'] ?? 0));

        return DB::transaction(
            fn() =>
            MerchantCommission::updateOrCreate($key, $data)
        );
    }

    /**
     * دعم إرسال Array لإنشاء أكتر من سطر (مثلاً لكل دولة/ولاية).
     */
    public function upsertManyForMerchant(int $merchantId, array $items): void
    {
        foreach ($items as $item) {
            $this->upsertForMerchant($merchantId, $item);
        }
    }
    /**
     * Calculate and record merchant commission transaction for a delivered shipment.
     * DEPRECATED: Use MerchantTransactionService::recordShipmentFees instead.
     *
     * @param \App\Models\Shipment $shipment
     * @return array
     */
    public function calculateAndRecord(\App\Models\Shipment $shipment)
    {
        return app(\App\Services\MerchantTransactionService::class)->recordShipmentFees($shipment);
    }
}
