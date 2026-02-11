<?php

namespace Database\Seeders;

use App\Models\DeliveryException;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeliveryExceptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DeliveryException::create([
            "name" => "Some thing",
            "description" => "some description",
            "proof_required" => 1
        ]);
    }
}
