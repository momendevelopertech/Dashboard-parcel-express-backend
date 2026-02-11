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
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('truck_id');
            $table->enum('maintenance_type', [
                'oil_change',
                'tire_replacement',
                'brake_service',
                'general_inspection',
                'filter_change',
                'others'
            ])->default('oil_change');
            $table->timestamp('scheduled_at');
            $table->enum('status', ['pending', 'completed', 'overdue'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('truck_id')->references('id')->on('trucks')->onDelete('cascade');
            $table->index('maintenance_type');
            $table->index('status');
            $table->index('scheduled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_schedules');
    }
};
