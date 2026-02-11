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
        Schema::create('merchant_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('users');
            $table->foreignId('country_id')->nullable();
            $table->foreignId('governorate_id')->nullable();
            $table->foreignId('state_id')->nullable();
            $table->foreignId('place_id')->nullable();
            $table->foreignId('city_id')->nullable();
            $table->string('name');
            $table->string('contact');
            $table->string('location')->nullable();
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_branches');
    }
};
