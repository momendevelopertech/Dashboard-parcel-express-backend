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
        Schema::create('consignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId("country_id")->nullable();
            $table->foreignId("governorate_id")->nullable();
            $table->foreignId("state_id")->nullable();
            $table->foreignId("place_id")->nullable();
            $table->foreignId("city_id")->nullable();
            
            $table->string("name")->nullable();
            $table->string("email")->nullable();
            $table->string("country_key_cellphone")->nullable();
            $table->string("cellphone")->nullable();
            $table->string("country_key_alternatePhone")->nullable();
            $table->string("alternatePhone")->nullable();
            $table->string("district")->nullable();
            $table->string("zipcode")->nullable();
            $table->text("streetAddress")->nullable();
            $table->string("identify")->nullable();
            $table->string("taxNumber")->nullable();
            $table->string("longitude")->nullable();
            $table->string("latitude")->nullable();
            $table->text("location")->nullable();
            $table->text("location_url")->nullable();
            $table->string("address_update_url")->nullable();
            $table->text("update_token")->nullable();
            $table->timestamp("token_expires_at")->nullable();
            $table->boolean("address_confirmed")->default(false);
            $table->boolean("is_guest")->default(false);
            $table->nullableMorphs("owner");

            $table->string('address_update_otp')->nullable();
            $table->timestamp('address_update_otp_expires_at')->nullable();
            $table->timestamp('address_update_verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consignees');
    }
};
