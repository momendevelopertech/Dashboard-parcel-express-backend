<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('merchant_pickup_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('manifest_id')->nullable()->unique();
            $table->foreignId('merchant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('no_of_shipments')->default(0);
            $table->string('note')->nullable();
            $table->nullableMorphs("owner");
            $table->enum('status', ['created', 'pending', 'pickup_completed', 'cancelled', 'picked', 'to_pickup'])->default('to_pickup');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('pickup_request_id')->nullable()->constrained('pickup_requests', 'id')->nullOnDelete();
            $table->dateTime('scheduled_at')->nullable();
            $table->unsignedInteger('confirmed_shipments_count')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_pickup_tasks');
    }
};
