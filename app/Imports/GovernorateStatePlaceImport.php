<?php

namespace App\Imports;

use App\Models\City;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\State;
use App\Models\Place;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class GovernorateStatePlaceImport implements ToCollection, WithHeadingRow
{
    /**
     * @param Collection $rows
     * @return void
     */
    public function collection(Collection $rows)
    {
        $countryId = Country::where('name','Oman')->value('id'); // Oman 

        
        $stateIds = State::where('country_id', $countryId)->pluck('id');

      
        City::whereIn('state_id', $stateIds)->delete();

        State::where('country_id', $countryId)->delete();

        foreach ($rows as $row) {
            // Store Governorate if not already present
            $governorate = Governorate::firstOrCreate(
                ['en_name' => $row['governorates_english']],
                ['ar_name' => $row['governorates_arabic'], 'country_id' => $countryId]
            );

            // Store State if not already present
            $state = State::firstOrCreate(
                ['en_name' => $row['state_english'], 'governorate_id' => $governorate->id],
                ['ar_name' => $row['state_arabic'], 'country_id' => $countryId]
            );

            // Split places into arrays
            $places_en = explode(',', $row['places_english']);
            $row['places_arabic'] = str_replace([',', '،'], ',', $row['places_arabic']);
            $places_ar = explode(',', $row['places_arabic']);

            foreach ($places_en as $index => $place_en) {
                $place_en = trim($place_en);
                $place_ar = isset($places_ar[$index]) ? trim($places_ar[$index]) : null;

                Place::firstOrCreate(
                    ['en_name' => $place_en, 'state_id' => $state->id],
                    ['ar_name' => $place_ar]
                );
            }
        }
    }

}
