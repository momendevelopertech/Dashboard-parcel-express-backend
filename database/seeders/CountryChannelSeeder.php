<?php

namespace Database\Seeders;

use App\Models\CountryChannel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CountryChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::dropIfExists('country_channels');
        DB::unprepared(file_get_contents(database_path('../sqls/country_channels.sql')));
    }
}
