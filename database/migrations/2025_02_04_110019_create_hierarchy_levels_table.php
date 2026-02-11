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
        Schema::create('hierarchy_levels', function (Blueprint $table) {
            $table->id();
            $table->string('role_name')->unique(); // e.g., Supervisor, Manager, Branch Owner
            $table->integer('level'); // Defines the level shipment (1 = lowest, 3 = highest)
            $table->text('description')->nullable();
            $table->nullableMorphs('owner');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hierarchy_levels');
    }
};
