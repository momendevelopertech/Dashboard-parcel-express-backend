<?php

namespace App\Services;

use App\Models\Consignee;

class ConsigneeLocationService
{
    public function setLatLng(Consignee $consignee)
    {
        if ($consignee->latitude && $consignee->longitude) {
            return;
        }

        $locationSources = [
            'place' => $consignee->place,
            'state' => $consignee->state,
            'governorate' => $consignee->governorate
        ];

        foreach ($locationSources as $source) {
            if ($source && isset($source->lat, $source->lng)) {
                $consignee->latitude = $source->lat;
                $consignee->longitude = $source->lng;
                return;
            }
        }
    }
}
