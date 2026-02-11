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
        Schema::table('driver_bonuses_transactions', function (Blueprint $table) {
            $table->boolean('isPaid')->default(false)->after('active')->index()->comment('Indicates if the bonus has been paid to the driver');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_bonuses_transactions', function (Blueprint $table) {
            $table->dropColumn('isPaid');
        });
    }
};
