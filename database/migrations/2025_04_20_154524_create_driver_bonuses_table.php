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
        Schema::create('driver_bonuses', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs("owner");
            $table->foreignId("driver_id");
            $table->foreignId("state_id");
            $table->decimal("delivery_bonus");
            $table->decimal("pickup_bonus");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_bonuses');
    }
};
