<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverRunsheetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'confirmed_at' => $this->confirmed_at,
            'created_at' => $this->created_at,

            // Counters
            'shipments_count' => $this->when(isset($this->shipments_count), $this->shipments_count),
            'delivered_count' => $this->when(isset($this->delivered_count), $this->delivered_count),
            'not_delivered_count' => $this->when(isset($this->not_delivered_count), $this->not_delivered_count),
            'returned_count' => $this->when(isset($this->returned_count), $this->returned_count),
            'holding_count' => $this->when(isset($this->holding_count), $this->holding_count),
            'difference_count' => $this->when(isset($this->difference_count), $this->difference_count),

            // علاقات خفيفة (متاحة لأننا عملنا eager load)
            'driver' => $this->whenLoaded('driver', function () {
                return [
                    'id' => $this->driver->id,
                    'name' => $this->driver->name,
                ];
            }),

            'invoice' => $this->whenLoaded('invoice', function () {
                return [
                    'id' => $this->invoice->id,
                    'invoice_no' => $this->invoice->invoice_no ?? null,
                    'total' => $this->invoice->total ?? null,
                    'created_at' => $this->invoice->created_at,
                ];
            }),

            'submission' => $this->whenLoaded('submission', function () {
                return [
                    'id' => $this->submission->id,
                    'status' => $this->submission->status ?? null, // من alias أو null
                    'created_at' => $this->submission->created_at,
                ];
            }),

            // ملاحظــة:
            // عمداً ما حملناش assigned_shipments هنا عشان ما نرجعش 200+ shipment في نفس الصفحة.
            // اعرض أزرار/روابط بالـ UI تجيب أوامر الرانشيت من Endpoint منفصل (أو نزود لاحقاً include_shipments=true).
        ];
    }
}
