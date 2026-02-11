<?php

namespace Database\Seeders;

use App\Models\DriverBonus;
use App\Models\State;
use Illuminate\Database\Seeder;

class DriverBonusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $states = State::select('id')->get();
        $drivers = [5, 6, 7]; // Drivers to seed bonuses for

        foreach ($drivers as $driverId) {
            foreach ($states as $state) {
                DriverBonus::create([
                    'driver_id' => $driverId,
                    'state_id' => $state->id,
                    'delivery_bonus' => "0.600",
                    'pickup_bonus' => "0.600",
                ]);
            }
        }
    }
}
