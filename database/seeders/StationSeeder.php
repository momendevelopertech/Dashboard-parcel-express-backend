<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\FacilityAccount;
use App\Models\Station;
use App\Models\Hub;
use App\Models\Role;
use App\Models\StationUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StationSeeder extends Seeder
{
    public function run()
    {
        $hub1 = Hub::first();

        // $station = Station::create([
        //     'hub_id' => $hub1->id,
        //     "id" => 1,
        //     'name' => 'Muscat Station',
        //     'country_id' => 165,
        //     'state_id' => 1,
        //     'governorate_id' => 1,
        //     'place_id' => 1,
        //     'city_id' => 1,
        //     'location' => "H76W+X4 Muscat, Oman",
        //     "lat" => 23.56249683143331,
        //     "lng" => 58.295339736751416,
        //     'contact_number' => '1231231234',
        // ]);

        // Account::create([
        //     "accountable_id" => $station->id,
        //     "accountable_type" => Station::class,
        // ]);


        $station_2 = Station::create([
            'hub_id' => $hub1->id,
            "id" => 2,
            'name' => 'Salalah Station',
            'country_id' => 165,
            'state_id' => 7,
            'governorate_id' => 2,
            'place_id' => 421,
            'city_id' => 1,
            'location' => "22PW+HH3، صلالة، Oman",
            "lat" => 17.88926048120438,
            "lng" => 52.648222538883225,
            'contact_number' => '72223696',
        ]);

        Account::create([
            "accountable_id" => $station_2->id,
            "accountable_type" => Station::class,
        ]);

        $station_3 = Station::create([
            'hub_id' => $hub1->id,
            "id" => 3,
            'name' => 'Sohar Station',
            'country_id' => 165,
            'state_id' => 29,
            'governorate_id' => 5,
            'place_id' => 29,
            'city_id' => 3304,
            'location' => "9MM6+828, Sohar, Oman",
            "lat" => 23.96501895413277,
            "lng" => 57.08844129188081,
            'contact_number' => '98225395',
        ]);

        Account::create([
            "accountable_id" => $station_3->id,
            "accountable_type" => Station::class,
        ]);

        $station_4 = Station::create([
            'hub_id' => $hub1->id,
            "id" => 4,
            'name' => 'Al Dhahirah Station',
            'country_id' => 165,
            'state_id' => 2,
            'governorate_id' => 1,
            'place_id' => 26,
            'city_id' => 22,
            'location' => "6FJW+GMF, Ibri, Oman",
            "lat" => 23.595546877542596,
            "lng" => 58.5471737196358,
            'contact_number' => '72600073',

        ]);

        Account::create([
            "accountable_id" => $station_4->id,
            "accountable_type" => Station::class,
        ]);

        // $station_role = Role::create(["name" => "StationAdmin", "guard_name" => "web"]);
        // $station_user = User::create([
        //     'owner_id' => $station->id,
        //     'owner_type' => get_class($station),
        //     'name' => 'Muscat Station Admin',
        //     'email' => 'ms@gmail.com',
        //     'phone' => "+9123456789",
        //     'password' => Hash::make('Aa@123456')
        // ]);
        // StationUser::create([
        //     "user_id" => $station_user->id,
        //     "station_id" => $station->id
        // ]);

        // $station_user_2 = User::create([
        //     'owner_id' => $station_2->id,
        //     'owner_type' => get_class($station_2),
        //     'name' => 'Dhufur Station Admin',
        //     'email' => 'ds@gmail.com',
        //     'phone' => "+9123456789",
        //     'password' => Hash::make('Aa@123456')
        // ]);
        // StationUser::create([
        //     "user_id" => $station_user_2->id,
        //     "station_id" => $station_2->id
        // ]);

        // $station_user_3 = User::create([
        //     'owner_id' => $station_3->id,
        //     'owner_type' => get_class($station_3),
        //     'name' => 'Sohar Station Admin',
        //     'email' => 'soharadmin@gmail.com',
        //     'phone' => "+9123456789",
        //     'password' => Hash::make('Aa@123456')
        // ]);
        // StationUser::create([
        //     "user_id" => $station_user_3->id,
        //     "station_id" => $station_3->id
        // ]);

        // $station_user->assignRole($station_role);
        // $station_user_2->assignRole($station_role);
        // $station_user_3->assignRole($station_role);
    }
}
