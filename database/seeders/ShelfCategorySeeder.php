<?php

namespace Database\Seeders;

use App\Models\ShelfCategory;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ShelfCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ShelfCategory::create([
            'name' => 'Future Delivery',
            'barcode' => 'PE23232',
            'owner_type' => 'App\Models\Hub',
            'owner_id' => 1,
        ]);

        ShelfCategory::create([
            'name' => 'Cancelled',
            'barcode' => 'PE23232',
            'owner_type' => 'App\Models\Hub',
            'owner_id' => 1,
        ]);

        ShelfCategory::create(
            [
                'name' => 'RTO',
                'barcode' => 'PE23231',
                'owner_type' => 'App\Models\Hub',
                'owner_id' => 1,
            ]
        );
    }
}
