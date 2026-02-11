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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId("country_id")->nullable();
            $table->foreignId("governorate_id")->nullable();
            $table->foreignId("state_id")->nullable();
            $table->foreignId("place_id")->nullable();
            $table->string("name")->nullable();
            $table->string("email")->nullable();
            $table->string("cellphone")->nullable();
            $table->text("streetAddress")->nullable();
            $table->string("longitude")->nullable();
            $table->string("latitude")->nullable();
            $table->text("location")->nullable();
            $table->boolean("is_guest")->default(0);
            $table->nullableMorphs("owner");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
