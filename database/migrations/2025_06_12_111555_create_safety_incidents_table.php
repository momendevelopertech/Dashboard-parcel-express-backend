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
        Schema::create('safety_incidents', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->string('location');
            $table->text('description');
            $table->enum('severity', ['Low', 'Medium', 'High'])->default('Low');
            $table->enum('status', ['Open', 'Under Investigation', 'Resolved'])->default('Open');
            $table->foreignId('reported_by')->constrained('users', 'id')->onDelete('cascade');
            $table->foreignId('assigned_to')->nullable()->constrained('users', 'id')->onDelete('set null');
            $table->text('investigation_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('safety_incidents');
    }
};
