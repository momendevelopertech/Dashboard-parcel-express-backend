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
        Schema::create('driver_shipment_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_tracking_no')->nullable();
            $table->foreignId('shipment_id')->constrained('shipments', 'id')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users', 'id')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('from_merchant')->default(false);
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_shipment_assignments');
    }
};
