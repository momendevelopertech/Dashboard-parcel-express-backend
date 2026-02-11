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
        Schema::table('merchant_pickup_tasks', function (Blueprint $table) {
            $table->double('received_amount')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_pickup_tasks', function (Blueprint $table) {
           $table->dropColumn('received_amount');
           $table->dropColumn('proof');
        });
    }
};
