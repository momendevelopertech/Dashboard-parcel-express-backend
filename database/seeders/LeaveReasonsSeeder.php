<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LeaveReasonsSeeder extends Seeder
{
    public function run()
    {
        DB::table('leave_reasons')->insert([
            ['name_en' => 'Sick Leave', 'name_ar' => 'إجازة مرضية'],
            ['name_en' => 'Annual Leave', 'name_ar' => 'إجازة سنوية'],
            ['name_en' => 'Emergency Leave', 'name_ar' => 'إجازة طارئة'],
            ['name_en' => 'Travel Leave', 'name_ar' => 'إجازة سفر'],
            ['name_en' => 'Family Leave', 'name_ar' => 'إجازة عائلية'],
        ]);
    }
}
