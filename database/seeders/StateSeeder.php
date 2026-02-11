<?php

namespace Database\Seeders;

use App\Models\State;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
class StateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('state_zones');

        Schema::dropIfExists('states');

        Schema::enableForeignKeyConstraints();

        DB::unprepared(file_get_contents(database_path('../sqls/states.sql')));
        $path = collect(glob(database_path('migrations/*create_state_zones_table*.php')))->first();
        if ($path) {
            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);

            Artisan::call('migrate:refresh', [
                '--path' => $relativePath,
                '--force' => true,
            ]);
        } else {
            throw new \RuntimeException('state_zones migration file not found.');
        }
    }
}
