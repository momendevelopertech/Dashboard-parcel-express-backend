<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_pickup_shipments', function (Blueprint $table) {
            if (Schema::hasColumn('merchant_pickup_shipments', 'proof_path_2')) {
                $table->dropColumn('proof_path_2');
            }
        });
    }

    public function down(): void
    {
        Schema::table('merchant_pickup_shipments', function (Blueprint $table) {
            $table->string('proof_path_2', 191)->nullable();
        });
    }
};
