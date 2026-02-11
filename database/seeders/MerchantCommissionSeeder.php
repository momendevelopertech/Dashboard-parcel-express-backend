<?php

namespace Database\Seeders;

use App\Models\MerchantCommission;
use App\Models\State;
use Illuminate\Database\Seeder;

class MerchantCommissionSeeder extends Seeder
{
    public function run(): void
    {
        $states = State::select('id')->get();

        foreach ($states as $state) {
            info($state);
            MerchantCommission::create([
                'merchant_id' => 4,
                'country_id' => 165,
                'state_id' => $state->id,
                'delivery_fee' => 1,
                'return_fee' => 1,
            ]);
        }
    }
}
