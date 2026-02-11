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
        Schema::create('driver_stops', function (Blueprint $table) {
            $table->id();
            // $table->string('list_id', 36);
            // $table->foreign('list_id')->references('id')->on('driver_stop_lists')->cascadeOnDelete();
            $table->foreignUuid('list_id')
                ->constrained('driver_stop_lists')
                ->cascadeOnDelete();

            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();

            $table->string('name', 255);
            $table->text('address');

            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);

            // $table->point('location')->storedAs('ST_SRID(POINT(`longitude`, `latitude`), 4326)');
            // $table->spatialIndex('location', 'idx_driver_stops_location');
            $table->index(['latitude', 'longitude'], 'idx_driver_stops_lat_lng');
            $table->text('notes')->nullable();
            $table->unsignedInteger('estimated_duration_minutes')->nullable();

            $table->boolean('is_optimized')->default(false);
            $table->unsignedInteger('optimized_shipment')->nullable();

            $table->string('place_id', 255)->nullable();
            $table->string('contact_person', 255)->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->text('delivery_instructions')->nullable();
            $table->boolean('is_priority')->default(false);

            $table->enum('status', ['pending', 'in_transit', 'delivered', 'failed', 'cancelled'])->default('pending');

            $table->timestamps();

            $table->index('list_id', 'idx_driver_stops_list_id');
            $table->index(['list_id', 'optimized_shipment'], 'idx_driver_stops_list_shipment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_stops');
    }
};
