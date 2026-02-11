<?php

namespace Database\Seeders;

use App\Models\ShipmentType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ShipmentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ShipmentType::create([
            "name" => "Standard",
            "days" => 3,
        ]);

        ShipmentType::create([
            "name" => "Fast Track",
            "days" => 1,
        ]);

        ShipmentType::create([
            "name" => "Express",
            "days" => 0,
        ]);
    }
}
