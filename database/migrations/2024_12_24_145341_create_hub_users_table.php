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
        Schema::create('hub_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->nullable()->constrained('users', 'id')->cascadeOnDelete();
            $table->foreignId("hub_id")->nullable()->constrained('hubs', 'id')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hub_users');
    }
};
