<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
class FinancialRequestResource extends JsonResource
{
    public function toArray($request)
    {
        $toDate = function ($value) {
            if (!$value)
                return null;
            return $value instanceof Carbon
                ? $value->toDateString()
                : Carbon::parse($value)->toDateString();
        };

        $toDateTime = function ($value) {
            if (!$value)
                return null;
            return $value instanceof Carbon
                ? $value->toDateTimeString()
                : Carbon::parse($value)->toDateTimeString();
        };

        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type,
            'status' => $this->status,
            'payee_name' => $this->type === 'driver_salary' && $this->payee
                ? $this->payee->pluck('name')->implode(', ')
                : ($this->payee ? $this->payee->name : null),
            'period' => $toDate($this->period_date),
            'amount' => (float) $this->amount,
            'notes' => $this->notes,
            'financial_proof' => $this->financial_proof,
            'created_at' => $toDateTime($this->created_at),      // ✅ آمن لو كانت string
            'reviewed_at' => $toDateTime($this->reviewed_at),     // ✅
            'requester' => [
                'type' => $this->whenLoaded('requester', fn() => class_basename($this->requester_type)),
                'id' => $this->whenLoaded('requester', fn() => $this->requester_id),
                'name' => $this->whenLoaded('requester', fn() => $this->requester->name ?? null),
            ],
            'creator' => $this->whenLoaded('creator', fn() => $this->creator?->only(['id', 'name'])),
            'reviewer' => $this->whenLoaded('reviewer', fn() => $this->reviewer?->only(['id', 'name'])),
        ];
    }
}
