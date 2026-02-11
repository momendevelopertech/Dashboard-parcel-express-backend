<?php

namespace Database\Seeders;

use App\Models\ShipperCommission;
use App\Models\State;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ShipperCommissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stateIds = State::pluck('id');

        foreach ($stateIds as $stateId) {
            ShipperCommission::updateOrCreate(
                [
                    'shipper_id' => 1,
                    'state_id'   => $stateId,
                ],
                [
                    'delivery_fee' => 1,
                ]
            );
        }
    }
}
