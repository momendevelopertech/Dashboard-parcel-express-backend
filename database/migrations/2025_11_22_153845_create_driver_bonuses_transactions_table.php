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
        Schema::create('driver_bonuses_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->onDelete('cascade');
            $table->string('shipment_tracking_no')->nullable()->index();
            $table->string('pre_id')->nullable()->index();
            $table->foreignId('state_id')->constrained('states')->onDelete('cascade');
            $table->foreignId('driver_runsheet_id')->nullable()->constrained('driver_runsheets')->onDelete('cascade');
            $table->decimal('bonus_amount', 10, 2);
            $table->decimal('bonus_rate', 10, 2)->comment('The bonus rate from DriverBonus.delivery_bonus');
            $table->string('reference')->nullable()->index();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Indexes for better query performance
            $table->index(['driver_id', 'state_id']);
            $table->index(['driver_runsheet_id', 'state_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_bonuses_transactions');
    }
};
