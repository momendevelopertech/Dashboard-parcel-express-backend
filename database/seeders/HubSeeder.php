<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\FacilityAccount;
use App\Models\Hub;
use App\Models\HubUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HubSeeder extends Seeder
{
    public function run()
    {
        $hub_1 = Hub::create([
            'name' => 'Muscat Hub',
            'location' => 'Location 1',
            'contact_number' => '1234567890',
            'country_id' => 165,
            'state_id' => 1,
            'governorate_id' => 1,
            'place_id' => 1,
            'city_id' => 1,
            'lat' => 23.5859,
            'lng' => 58.4059,
        ]);
        Account::create([
            "accountable_id" => $hub_1->id,
            "accountable_type" => Hub::class,
        ]);
    }
}
