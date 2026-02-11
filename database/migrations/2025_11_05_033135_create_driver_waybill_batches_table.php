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
        Schema::create('driver_waybill_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users', 'id');
            $table->foreignId('created_by')->constrained('users', 'id');
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->index(['driver_id', 'created_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_waybill_batches');
    }
};
