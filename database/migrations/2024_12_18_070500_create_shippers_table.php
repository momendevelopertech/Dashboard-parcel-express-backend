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
        Schema::create('shippers', function (Blueprint $table) {
            $table->id();
            // Personal Information
            $table->string('name');
            $table->string('email')->unique()->nullable(); // Email address for contact
            $table->string('contact')->nullable(); // Primary contact number
            $table->string('country_key_contact')->nullable(); // Primary contact number
            $table->string('alternative_contact')->nullable(); // Alternative contact number
            $table->string('alternative_country_key_contact')->nullable(); // Alternative contact number
        
            // Address Information
            $table->foreignId('country_id')->nullable()->constrained('countries', 'id')->nullOnDelete();
            $table->foreignId('state_id')->nullable();
            $table->foreignId('governorate_id')->nullable();
            $table->foreignId('place_id')->nullable();
            // $table->foreignId('city_id')->nullable()->constrained('cities', 'id')->nullOnDelete();
            $table->text('address')->nullable(); // Detailed street address
            $table->string('zip_code')->nullable(); // Postal/ZIP code
        
            // Business Information
            $table->string('website')->nullable(); // Business website
        
            // Additional Information
            $table->text('notes')->nullable(); // Any special notes or instructions
            $table->boolean('is_active')->default(true); // Status to enable/disable a shipper
            $table->morphs("owner");
        
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shippers');
    }
};
