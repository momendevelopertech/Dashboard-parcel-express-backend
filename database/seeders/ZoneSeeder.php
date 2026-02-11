<?php

namespace Database\Seeders;

use App\Models\Hub;
use App\Models\Station;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ZoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $muscat_geojson = '{
        //     "type": "Polygon",
        //     "coordinates": [
        //         [
        //             [58.031042, 23.7223649],
        //             [58.0063228, 23.5890278],
        //             [58.0832271, 23.4202755],
        //             [58.4759883, 23.3169028],
        //             [58.7863521, 23.1579051],
        //             [58.9429073, 23.0568561],
        //             [59.0774898, 23.1831555],
        //             [58.5473994, 23.672065],
        //             [58.031042, 23.7223649]
        //         ]
        //     ]
        // }';

        // DB::table('zones')->insert([
        //     'owner_type'  => Hub::class,
        //     'owner_id'    => 1,
        //     'name'        => 'Muscat Zone',
        //     'coordinates' => DB::raw("ST_GeomFromGeoJSON('{$muscat_geojson}')"),
        //     'created_at'  => now(),
        //     'updated_at'  => now(),
        // ]);

        // $dhufur_geojson = '{
        //     "type": "Polygon",
        //     "coordinates": [
        //         [
        //             [52.1357893, 18.7293845],
        //             [53.1025862, 16.6308097],
        //             [55.3547835, 16.9936368],
        //             [53.932054, 19.103531],
        //             [52.1357893, 18.7293845]
        //         ]
        //     ]
        // }';

        $user = User::where('email', 'ds@gmail.com')->first();
        // DB::table('zones')->insert([
        //     'name'        => 'Salalah Zone',
        //     'owner_type'  => Station::class,
        //     'owner_id'    => 2,
        //     'coordinates' => DB::raw("ST_GeomFromGeoJSON('{$dhufur_geojson}')"),
        //     'created_at'  => now(),
        //     'updated_at'  => now(),
        // ]);

        // $alkhaboura_geojson = '{
        //     "type": "Polygon",
        //     "coordinates": [
        //         [
        //             [55.770605, 24.5095108],
        //             [55.2414923, 22.6991848],
        //             [55.7856523, 21.4464907],
        //             [57.1237019, 21.1796545],
        //             [58.0145625, 22.3321592],
        //             [57.7673702, 23.8078302],
        //             [56.3611202, 25.0904429],
        //             [55.7561805, 24.9634686],
        //             [55.770605, 24.5095108]
        //         ]
        //     ]
        // }';

        $user = User::where('email', 'bs@gmail.com')->first();
        // DB::table('zones')->insert([
        //     'name'        => 'Alkhaboura Zone',
        //     'owner_type'  => Station::class,
        //     'owner_id'    => 3,
        //     // 'coordinates' => DB::raw("ST_GeomFromGeoJSON('{$alkhaboura_geojson}')"),
        //     'created_at'  => now(),
        //     'updated_at'  => now(),
        // ]);
    }
}