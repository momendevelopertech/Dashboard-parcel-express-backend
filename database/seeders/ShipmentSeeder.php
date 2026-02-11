<?php

namespace Database\Seeders;

use App\Models\Shipment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PDO;

class ShipmentSeeder extends Seeder
{
    public function run()
    {
        DB::disableQueryLog();
        $total = 100;
        $batch = 10;
        $this->command->getOutput()->progressStart($total);

        for ($i = 0; $i < $total; $i += $batch) {
            Shipment::factory()->count($batch)->create();
            $this->command->getOutput()->progressAdvance($batch);
        }

        $this->command->getOutput()->progressFinish();
    }
}
