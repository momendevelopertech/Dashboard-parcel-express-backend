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
        Schema::create('reverse_pickup_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('manifest_id')->nullable()->unique();
            $table->string('ref', 20)->unique();

            // Foreign key columns (nullable unsignedBigInteger)
            $table->unsignedBigInteger('merchant_id')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('reverse_pickup_request_id')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();

            $table->unsignedInteger('no_of_shipments')->default(0);
            $table->unsignedInteger('picked_shipments_no')->default(0);
            $table->text('note')->nullable();
            $table->enum('status', ['pending', 'to_pickup', 'pickup', 'completed', 'cancelled'])->default('pending');
            $table->nullableMorphs('owner');
            $table->dateTime('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('merchant_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reverse_pickup_request_id')->references('id')->on('reverse_pickup_requests')->onDelete('set null');
            $table->foreign('assigned_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reverse_pickup_tasks');
    }
};
