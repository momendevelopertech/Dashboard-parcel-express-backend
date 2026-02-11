<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('driver_bonuses', function (Blueprint $table) {
            $table->decimal('return_bonus', 10, 3)->default(0)->after('pickup_bonus');
            $table->decimal('return_pickup_bonus', 10, 3)->default(0)->after('return_bonus');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_bonuses', function (Blueprint $table) {
            $table->dropColumn(['return_bonus', 'return_pickup_bonus']);
        });
    }
};
