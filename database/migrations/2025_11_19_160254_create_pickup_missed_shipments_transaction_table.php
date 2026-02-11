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
        Schema::create('pickup_missed_shipments_transaction', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments', 'id')->cascadeOnDelete();
            $table->foreignId('pickup_task_id')->nullable()->constrained('merchant_pickup_tasks', 'id')->nullOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->timestamps();

            $table->index(['shipment_id']);
            $table->index(['merchant_id']);
            $table->index(['driver_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pickup_missed_shipments_transaction');
    }
};
