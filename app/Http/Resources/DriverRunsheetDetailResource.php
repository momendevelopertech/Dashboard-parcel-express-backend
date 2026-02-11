<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class DriverRunsheetDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */

    private function resolveTimezone(Request $request): string
    {
      
        $user = $request->user();

        $tz = $user && method_exists($user, 'timezone')
            ? $user->timezone()
            : null;

        return in_array($tz, \DateTimeZone::listIdentifiers())
            ? $tz
            : config('app.timezone');
    }




    public function toArray(Request $request): array
    {
        $data = $this->toArraySafe($this->resource);
        $tz =   authFacilityModel()['timezone'];

        $utc = Carbon::createFromFormat('Y-m-d H:i:s', $data['created_at'], 'UTC');

        return [
        'id' => $data['id'] ?? null,
        'status' => $data['status'] ?? null,
        'created_at' => $utc->copy()->setTimezone($tz)->format('Y-m-d H:i:s'),
        'total_delivered_COD' => $data['total_delivered_COD'] ?? '0',
        'shipments' => $this->formatShipments($data['shipments'] ?? null),
        ];
    }

    /**
     * Convert to array safely handling objects
     */
    private function toArraySafe($item)
    {
        if (is_array($item)) {
            return $item;
        }
        if (is_object($item) && method_exists($item, 'toArray')) {
            return $item->toArray();
        }
        return (array) $item;
    }

    /**
     * Get value from array or object
     */
    private function getValue($item, $key, $default = null)
    {
        if (is_array($item)) {
            return $item[$key] ?? $default;
        }
        if (is_object($item)) {
            return $item->{$key} ?? $default;
        }
        return $default;
    }

    /**
     * Format shipments data
     */
    private function formatShipments($shipments): ?array
    {
        if (!$shipments) {  
            return null;
        }

        $shipmentsData = $this->getValue($shipments, 'data', []);

        return [
            'data' => collect($shipmentsData)->map(function ($item) {
                return [
                    'id' => $this->getValue($item, 'id'),
                    'status' => $this->getValue($item, 'status'),
                    'shipment' => $this->formatShipment($this->getValue($item, 'shipment')),
                ];
            })->toArray(),
            'pagination' => $this->getValue($shipments, 'pagination'),
        ];
    }

    /**
     * Format single shipment
     */
    private function formatShipment($shipment): ?array
    {
        if (!$shipment) {
            return null;
        }

        return [
            'id' => $this->getValue($shipment, 'id'),
            'tracking_no' => $this->getValue($shipment, 'tracking_no'),
            'is_return' => (bool) $this->getValue($shipment, 'is_return', false),
            'return_to_type' => $this->getValue($shipment, 'return_to_type'),
            'consignee_id' => $this->getValue($shipment, 'consignee_id'),
            'value' => $this->getValue($shipment, 'value'),
            'total_cod' => $this->getValue($shipment, 'total_cod'),
            'delivery_fee' => $this->getValue($shipment, 'delivery_fee'),
            'payment_type' => $this->getValue($shipment, 'payment_type'),
            'status' => $this->getValue($shipment, 'status'),
            'customer_name' => $this->getValue($shipment, 'customer_name'),
            'fee_payer' => $this->getValue($shipment, 'fee_payer'),
            'exception_type' => $this->getValue($shipment, 'exception_type'),
            'consignee' => $this->formatConsignee($this->getValue($shipment, 'consignee')),
            "delivery_fee_before_discount" => $this->getValue($shipment, 'delivery_fee_before_discount'),
        ];
    }

    /**
     * Format consignee data
     */
    private function formatConsignee($consignee): ?array
    {
        if (!$consignee) {
            return null;
        }

        return [
            'id' => $this->getValue($consignee, 'id'),
            'name' => $this->getValue($consignee, 'name'),
            'cellphone' => $this->getValue($consignee, 'cellphone'),
            'alternatePhone' => $this->getValue($consignee, 'alternatePhone'),
            'streetAddress' => $this->getValue($consignee, 'streetAddress'),
            'governorate' => $this->formatLocation($this->getValue($consignee, 'governorate')),
            'state' => $this->formatLocation($this->getValue($consignee, 'state')),
            'place' => $this->formatPlace($this->getValue($consignee, 'place')),
        ];
    }

    /**
     * Format location (governorate/state)
     */
    private function formatLocation($location): ?array
    {
        if (!$location) {
            return null;
        }

        return [
            'en_name' => $this->getValue($location, 'en_name'),
            'ar_name' => $this->getValue($location, 'ar_name'),
        ];
    }

    /**
     * Format place
     */
    private function formatPlace($place): ?array
    {
        if (!$place) {
            return null;
        }

        return [
            'en_name' => $this->getValue($place, 'en_name'),
            'ar_name' => $this->getValue($place, 'ar_name'),
        ];
    }
}
