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
        Schema::create('hubs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('location');
            $table->text('address')->nullable();
            $table->nullableMorphs('owner');
            $table->string('contact_number', 15)->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries', 'id')->nullOnDelete();
            $table->foreignId("governorate_id")->nullable(); // ->constrained('governorates')->nullOnDelete();
            $table->foreignId("state_id")->nullable(); //->constrained('states')->nullOnDelete();
            $table->foreignId("place_id")->nullable(); //->constrained('places')->nullOnDelete();
            $table->foreignId("city_id")->nullable(); //->constrained('cities')->nullOnDelete();
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hubs');
    }
};
