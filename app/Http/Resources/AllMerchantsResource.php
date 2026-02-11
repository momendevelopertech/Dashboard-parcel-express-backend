<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllMerchantsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->merchant->user_id,
            'country_id' => $this->merchant->country_id,
            'governorate_id' => $this->merchant->governorate_id,
            'state_id' => $this->merchant->state_id,
            'place_id' => $this->merchant->place_id,
            'address' => $this->merchant->address,
            'country_code' => $this->merchant->country_code,
            'contact_no' => $this->merchant->contact_no,
            'currency' => $this->merchant->currency,
            'facility_to_facility_fees' => $this->merchant->facility_to_facility_fees,
            'lat' => $this->merchant->lat,
            'lng' => $this->merchant->lng,
            'is_guest' => $this->merchant->is_guest,
            'owner_id' => $this->merchant->owner_id,
            'owner_type' => $this->merchant->owner_type,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }
}
