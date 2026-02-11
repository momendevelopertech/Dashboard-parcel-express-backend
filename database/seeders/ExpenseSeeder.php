<?php

namespace Database\Seeders;

use App\Models\Expense;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExpenseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        DB::disableQueryLog();
        $total = 1000;
        $batch = 10;
        $this->command->getOutput()->progressStart($total);

        for ($i = 0; $i < $total; $i += $batch) {
            Expense::factory()->count($batch)->create();
            $this->command->getOutput()->progressAdvance($batch);
        }

        $this->command->getOutput()->progressFinish();
    }
}
