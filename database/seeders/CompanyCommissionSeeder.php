<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CompanyCommission;
use App\Models\State;

class CompanyCommissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stateIds = State::pluck('id');

        foreach ($stateIds as $stateId) {
            CompanyCommission::updateOrCreate(
                [
                    'company_id' => 1,
                    'state_id'   => $stateId,
                ],
                [
                    'delivery_fee' => 5,
                    'pickup_fee'   => 5,
                ]
            );
        }
    }
}
