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
        Schema::create('transfer_destinations', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs("owner");
            $table->foreignId('transfer_task_id');
            $table->morphs('origin');
            $table->morphs('destination');
            $table->string('status')->default(value: 'pending');
            $table->timestamp('loaded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_stops');
    }
};
