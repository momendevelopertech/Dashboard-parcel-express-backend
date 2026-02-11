<?php

namespace Database\Seeders;

use App\Models\Hub;
use Illuminate\Database\Seeder;

class HubTimezoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Oman timezone (Gulf Standard Time, GMT+4)
        Hub::query()
            ->where('id', 1)
            ->update([
                'timezone' => 'Asia/Muscat',
            ]);
    }
}


