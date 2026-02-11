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
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->foreignId("country_id")->nullable();
            $table->foreignId("governorate_id")->nullable();
            $table->foreignId("state_id")->nullable();
            $table->foreignId("place_id")->nullable();
            $table->foreignId("user_id")->nullable();
            $table->string("address")->nullable();
            $table->string("country_code")->nullable();
            $table->string("contact_no")->nullable();
            $table->double("lat", 14, 10)->nullable();
            $table->double("lng", 14, 10)->nullable();
            $table->string("currency")->nullable();
            $table->decimal("facility_to_facility_fees")->nullable();
            $table->nullableMorphs("owner");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
