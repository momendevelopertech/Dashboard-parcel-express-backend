<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Unit::create( [
            'name' => 'length',
            'owner_type' => 'App\Models\Hub',
            'owner_id' => 1,
        ]);

        Unit::create( 
            [
            'name' => 'Kg',
            'owner_type' => 'App\Models\Hub',
            'owner_id' => 1,
        ]);
    }
}
