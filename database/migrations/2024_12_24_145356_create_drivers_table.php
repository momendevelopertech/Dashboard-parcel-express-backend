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
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs("owner");
            $table->foreignId("user_id")->nullable()->constrained('users', 'id')->cascadeOnDelete();
            $table->foreignId("company_id")->nullable();
            $table->enum("status", ["pending", "approved", "rejected"])->default("pending");
            $table->text('rejection_reason')->nullable();
            $table->string("country_code")->nullable();
            $table->string("phone")->nullable();
            $table->string("id_card")->nullable();
            $table->string("license")->nullable();
            $table->string("car_ownership_id")->nullable();
            $table->boolean("is_guest")->default(0);
            $table->string("profile_image")->nullable();
            $table->string("company_name")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
