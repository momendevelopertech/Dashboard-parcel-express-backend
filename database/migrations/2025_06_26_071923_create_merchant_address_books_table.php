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
        Schema::create('merchant_address_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId("merchant_id")->constrained("users");
            $table->string("name");
            $table->string("email")->nullable();
            $table->string("cellphone");
            $table->string("alternatePhone")->nullable();
            $table->foreignId("country_id")->nullable();
            $table->foreignId("governorate_id")->nullable();
            $table->foreignId("state_id")->nullable();
            $table->foreignId("place_id")->nullable();
            $table->string("zipcode")->nullable();
            $table->string("streetAddress")->nullable();
            $table->string("location_url")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_address_books');
    }
};
