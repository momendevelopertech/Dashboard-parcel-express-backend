<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reverse_pickup_shipments', function (Blueprint $table) {
            // Rename first
            $table->renameColumn('pickup_proof', 'pickup_proofs');
        });

        Schema::table('reverse_pickup_shipments', function (Blueprint $table) {
            // Then change type
            $table->longText('pickup_proofs')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('reverse_pickup_shipments', function (Blueprint $table) {
            $table->string('pickup_proofs', 191)->nullable()->change();
        });

        Schema::table('reverse_pickup_shipments', function (Blueprint $table) {
            $table->renameColumn('pickup_proofs', 'pickup_proof');
        });
    }
};

