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
        Schema::create('unassigned_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->string("tracking_no")->nullable()->unique()->index();
            $table->foreignId('pickup_task_id')->nullable()->constrained('merchant_pickup_tasks', 'id')->nullOnDelete();
            $table->string('proof')->nullable();
            $table->timestamps();
            $table->index(['driver_id', 'merchant_id', 'pickup_task_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unassigned_shipments');
    }
};
