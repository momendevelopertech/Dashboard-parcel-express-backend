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
        Schema::create('driver_stop_lists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('total_stops')->default(0);
            $table->unsignedInteger('delivered_stops')->default(0);
            $table->unsignedInteger('pending_stops')->default(0);
            $table->unsignedInteger('failed_stops')->default(0);
            $table->decimal('total_distance_meters', 10, 2)->nullable();
            $table->unsignedInteger('total_duration_seconds')->nullable();
            $table->json('optimized_route_data')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('driver_id', 'idx_driver_stop_lists_driver_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_stop_lists');
    }
};
