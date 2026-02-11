<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\MerchantCommission;
use App\Models\Place;
use App\Models\State;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MerchantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // Split phone number
        // $phoneSplit = splitPhoneNumber("+9123456789");

        // $user = User::create([
        //     "owner_id" => "1",
        //     "owner_type" => "App\\Models\\Hub",
        //     "name" => "Hormuz",
        //     "email" => "hormuz@gmail.com",
        //     "country_code" => $phoneSplit['country_code'],
        //     "phone" => $phoneSplit['national_number'],
        //     "password" => Hash::make("Aa@123456")
        // ]);
        // $states = State::select('id')->get();
        // foreach ($states as $state) {
        //     MerchantCommission::create([
        //         'merchant_id' => $user->id,
        //         'country_id' => 165,
        //         'state_id' => $state->id,
        //         'base_delivery_fee' => 1,
        //         'delivery_fee' => 1,
        //         'return_fee' => 1,
        //     ]);
        // }

        // $merchant = Merchant::create([
        //     "user_id" => $user->id,
        //     "country_id" => 165,
        //     "governorate_id" => 5,
        //     "state_id" => 29,
        //     "place_id" => 1,
        //     "address" => "Muscat",
        //     "country_code" => $phoneSplit['country_code'],
        //     "contact_no" => $phoneSplit['national_number'],
        //     'currency' => "1",
        //     'facility_to_facility_fees' => "1",
        //     'lat' => "1",
        //     'lng' => "1"
        // ]);

        // // Create wallet for the merchant
        // Wallet::create([
        //     'user_id' => $user->id,
        //     'balance' => 0
        // ]);

    }
}
//── Merchants (4)
//│   ├── hormuz@gmail.com
//│   ├── merchant.muscat@parcelexpress.com
//│   ├── merchant.salalah@parcelexpress.com
//│   ├── merchant.sohar@parcelexpress.com
//│   └── merchant.ruwi@parcelexpress.com
