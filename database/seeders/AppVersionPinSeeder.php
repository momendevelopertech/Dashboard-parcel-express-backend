<?php

namespace Database\Seeders;

use App\Models\AppVersion;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AppVersionPinSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['android', 'ios'] as $platform) {
            AppVersion::updateOrCreate(
                ['platform' => $platform, 'app_code' => 'driver'],
                [
                    'min_supported_version' => '1.3.2',
                    'min_supported_build' => 69,
                    'latest_version' => '1.1.1',
                    'latest_build' => 27,
                    'force_all' => false,

                    'changelog' => 'Pinned 1.1.1+27',
                ]
            );
        }
        foreach (['android', 'ios'] as $platform) {
            AppVersion::updateOrCreate(
                ['platform' => $platform, 'app_code' => 'merchant'],
                [
                    'min_supported_version' => '2.0.4',
                    'min_supported_build' => 44,
                    'latest_version' => '1.0.0',
                    'latest_build' => 7,
                    'force_all' => false,
                    'changelog' => 'Pinned 1.0.0+7',
                    // 'store_url' => $platform === 'android'
                    //     ? 'https://play.google.com/store/apps/details?id=com.example.merchant'
                    //     : 'https://apps.apple.com/app/idYYYYYYYYY',
                ]
            );
        }
    }
}
