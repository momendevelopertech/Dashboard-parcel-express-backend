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
        Schema::create('inter_branch_transfer_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')
                ->constrained('inter_branch_transfers')
                ->cascadeOnDelete();

            $table->foreignId('shipment_id')
                ->constrained('shipments')
                ->cascadeOnDelete();

            $table->string('shipment_tracking_no');
            $table->decimal('amount', 18, 2)->default(0);
            $table->timestamps();

            $table->index('transfer_id', 'ibt_ship_transfer_idx');
            $table->index('shipment_tracking_no', 'ibt_ship_track_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inter_branch_transfer_shipments');
    }
};
