<?php

namespace Database\Seeders;

use App\Models\Place;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::unprepared("ALTER TABLE place_zone DROP FOREIGN KEY place_zone_place_id_foreign");

        Schema::dropIfExists('places');

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        DB::unprepared(file_get_contents(database_path('../sqls/places.sql')));

        DB::unprepared("
        ALTER TABLE place_zone
        ADD CONSTRAINT place_zone_place_id_foreign
        FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE CASCADE
    ");
    }
}
