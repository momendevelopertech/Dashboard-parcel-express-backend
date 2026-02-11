<?php

namespace Database\Seeders;

use App\Models\DriverAppSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DriverAppSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DriverAppSetting::create([
            "main_screen_image" => "image.png"
        ]);
    }
}
