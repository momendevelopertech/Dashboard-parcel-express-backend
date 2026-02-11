<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE driver_bonuses_transactions
            MODIFY action ENUM('pickup', 'delivery', 'return_pickup') NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE driver_bonuses_transactions
            MODIFY action ENUM('pickup', 'delivery') NULL
        ");
    }
};
