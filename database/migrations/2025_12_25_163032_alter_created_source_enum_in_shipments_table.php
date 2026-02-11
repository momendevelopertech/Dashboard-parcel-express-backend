<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            DB::statement("
                ALTER TABLE shipments
                MODIFY created_source 
                ENUM('dashboard', 'driver', 'unCreated')
                NULL
            ");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            DB::statement("
                ALTER TABLE shipments
                MODIFY created_source 
                ENUM('dashboard', 'driver')
                NULL
            ");
        });
    }
};
