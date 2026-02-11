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
        Schema::create('guest_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId("driver_id");
            $table->string("tracking_no")->nullable();
            $table->foreignId("governorate_id")->nullable();
            $table->foreignId("state_id")->nullable();
            $table->foreignId("place_id")->nullable();
            $table->foreignId("city_id")->nullable();
            $table->string("zipcode");
            $table->string("customer_name");
            $table->string("customer_phone");
            $table->string("streetAddress");
            $table->text("notes");
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string("location_url")->nullable();
            $table->string("payment_type")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guest_shipments');
    }
};
