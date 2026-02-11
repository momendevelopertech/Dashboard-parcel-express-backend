<?php

namespace Database\Seeders;

use App\Models\Shelf;
use App\Models\Branch;
use App\Models\ShelfItem;
use App\Models\Station;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class ShelfSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $shelves = [
            [
                'owner_id' => 3,
                'owner_type' => Station::class,
                'area' => 'A',
                'shelf_number' => 1,
                'layer_number' => 1,
                'partition_number' => 1,
                'barcode' => "PE434343",
                "location" => "A-1-1-1",
                "created_by" => 1,
                "category_id" => 1,
            ],

            [
                'owner_id' => 3,
                'owner_type' => Station::class,
                'area' => 'B',
                'shelf_number' => 1,
                'layer_number' => 1,
                'partition_number' => 1,
                'barcode' => "PE434331",
                "location" => "B-1-1-1",
                "created_by" => 1,
                "category_id" => 1,
            ],


        ];

        foreach ($shelves as $shelf) {
            Shelf::create($shelf);
        }
    }

    /**
     * Generate a unique barcode.
     *
     * @return string
     */
}
