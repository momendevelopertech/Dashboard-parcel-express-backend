<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Hub;
use App\Models\Place;
use App\Models\Scopes\ShipperScope;
use App\Models\Shipper;
use App\Models\ShipperSetting;
use Illuminate\Database\Seeder;

class ShipperSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Shipper::create([
            'id' => 1,
            'name' => 'Parcel Express',
            'email' => 'pe@gmail.com',
            'country_key_contact' => '+968',
            'contact' => '920012345',
            'country_id' => 165,
            'state_id' => 1,
            'governorate_id' => 1,
            'place_id' => 1,
            'address' => 'Aut maxime aliquid r',
            'zip_code' => '11820',
            'website' => 'https://www.pecesaliryro.cm',
            'notes' => 'Ipsum quaerat quia c',
            'is_active' => true,
            'owner_type' => "App\Models\Station",
            'owner_id' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ShipperSetting::create([
            "shipper_id" => 1,
            "failed_ofd_count" => 3,
            "rto_days" => 30,
        ]);

        Account::create([
            "accountable_id" => 4,
            "accountable_type" => Shipper::class,
        ]);

        Shipper::create([
            'id' => 2,
            'name' => 'GFS',
            'email' => 'gfs@gmail.com',
            'country_key_contact' => '+968',
            'contact' => '920012345',
            'country_id' => 165,
            'state_id' => 1,
            'governorate_id' => 1,
            'place_id' => 1,
            'address' => 'Aut maxime aliquid r',
            'zip_code' => '11820',
            'website' => 'https://www.pecesaliryro.cm',
            'notes' => 'Ipsum quaerat quia c',
            'is_active' => true,
            'owner_type' => "App\Models\Station",
            'owner_id' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ShipperSetting::create([
            "shipper_id" => 2,
            "failed_ofd_count" => 3,
            "rto_days" => 30,

        ]);

        Account::create([
            "accountable_id" => 5,
            "accountable_type" => Shipper::class,
        ]);
    }
}
