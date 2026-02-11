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
        Schema::create('company_commissions', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs("owner");
            $table->foreignId("company_id");
            $table->foreignId("state_id");
            $table->decimal("delivery_fee");
            $table->decimal("pickup_fee");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_commissions');
    }
};
