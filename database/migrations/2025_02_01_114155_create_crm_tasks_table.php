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
        Schema::create('crm_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id');
            $table->text('title')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->enum('status', ['created', 'to_call','hold', 'closed'])->default('created');
            $table->nullableMorphs('owner');
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_tasks');
    }
};
