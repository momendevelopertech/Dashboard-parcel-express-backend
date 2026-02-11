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
        Schema::table('governorates', function (Blueprint $table) {
            $table->boolean('isActive')->default(1)->after('country_id');
        });

        Schema::table('states', function (Blueprint $table) {
            $table->boolean('isActive')->default(1)->after('ar_name');
        });

        Schema::table('places', function (Blueprint $table) {
            $table->boolean('isActive')->default(1)->after('ar_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('governorates', function (Blueprint $table) {
            $table->dropColumn('isActive');
        });

        Schema::table('states', function (Blueprint $table) {
            $table->dropColumn('isActive');
        });

        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn('isActive');
        });
    }
};
