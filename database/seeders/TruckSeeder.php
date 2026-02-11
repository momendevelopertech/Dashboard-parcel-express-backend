<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TruckSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::select("
        INSERT INTO `trucks` (`id`, `truck_driver_id`, `owner_type`, `owner_id`, `barcode`, `number_plate`, `company`, `color`, `type`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 12, 'App\\Models\\Station', 3, '686F642455C18', '807', 'Arnold French Inc', 'Quisquam enim veniam', 'truck', 'active', 'Sit quo voluptatem', '2025-07-10 05:56:36', '2025-07-10 05:56:36');");
    }
}
