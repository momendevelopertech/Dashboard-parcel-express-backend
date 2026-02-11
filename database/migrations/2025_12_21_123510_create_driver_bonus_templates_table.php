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
        Schema::create('driver_bonus_templates', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('owner');
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->decimal('delivery_bonus', 10, 2)->default(0);
            $table->decimal('pickup_bonus', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_bonus_templates');
    }
};
