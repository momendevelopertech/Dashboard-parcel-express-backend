<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('driver_runsheet_shipments', function (Blueprint $table) {
            $table->string('timezone', 50)->default('Asia/Muscat')->after('driver_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_runsheet_shipments', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
