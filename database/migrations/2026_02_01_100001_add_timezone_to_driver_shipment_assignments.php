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
        Schema::table('driver_shipment_assignments', function (Blueprint $table) {
            // Add timezone field to store the timezone used when creating the assignment
            $table->string('timezone', 50)->default('Asia/Muscat')->after('driver_id');

            // Add index for timezone queries
            $table->index('timezone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_shipment_assignments', function (Blueprint $table) {
            $table->dropIndex(['timezone']);
            $table->dropColumn('timezone');
        });
    }
};
