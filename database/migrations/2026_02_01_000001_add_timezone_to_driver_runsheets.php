<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('driver_runsheets', function (Blueprint $table) {
            // Add timezone field to store the timezone used when creating the runsheet
            $table->string('timezone', 50)->default('Asia/Muscat')->after('driver_id');

            // Fix holded_at to be timestamp instead of string
            $table->timestamp('holded_at_new')->nullable()->after('confirmed_at');
        });

        // Migrate existing holded_at string values to timestamp
        // Only migrate if the value looks like a valid datetime
        DB::statement("
            UPDATE driver_runsheets 
            SET holded_at_new = STR_TO_DATE(holded_at, '%Y-%m-%d %H:%i:%s') 
            WHERE holded_at IS NOT NULL 
            AND holded_at REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}'
        ");

        Schema::table('driver_runsheets', function (Blueprint $table) {
            $table->dropColumn('holded_at');
        });

        Schema::table('driver_runsheets', function (Blueprint $table) {
            $table->renameColumn('holded_at_new', 'holded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_runsheets', function (Blueprint $table) {
            // Revert holded_at back to string
            $table->string('holded_at_old')->nullable();
        });

        DB::statement("
            UPDATE driver_runsheets 
            SET holded_at_old = DATE_FORMAT(holded_at, '%Y-%m-%d %H:%i:%s') 
            WHERE holded_at IS NOT NULL
        ");

        Schema::table('driver_runsheets', function (Blueprint $table) {
            $table->dropColumn('holded_at');
        });

        Schema::table('driver_runsheets', function (Blueprint $table) {
            $table->renameColumn('holded_at_old', 'holded_at');
            $table->dropColumn('timezone');
        });
    }
};
