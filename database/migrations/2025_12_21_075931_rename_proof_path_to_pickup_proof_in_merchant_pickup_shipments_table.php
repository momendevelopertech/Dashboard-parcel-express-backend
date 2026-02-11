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
        Schema::table('merchant_pickup_shipments', function (Blueprint $table) {
            $table->renameColumn('proof_path', 'pickup_proof');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_pickup_shipments', function (Blueprint $table) {
            $table->renameColumn('pickup_proof', 'proof_path');
        });
    }
};
