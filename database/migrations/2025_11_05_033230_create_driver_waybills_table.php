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
        Schema::create('driver_waybills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users', 'id');
            $table->foreignId('batch_id')
                ->nullable()
                ->constrained('driver_waybill_batches', 'id')
                ->nullOnDelete();
            $table->string('tracking_no')->unique();
            $table->boolean('used')->default(false);
            $table->timestamps();

            $table->index(['driver_id', 'used']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_waybills');
    }
};
