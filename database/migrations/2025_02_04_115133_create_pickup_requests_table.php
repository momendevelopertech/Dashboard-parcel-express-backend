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
        Schema::create('pickup_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 10)->unique();
            $table->foreignId('merchant_user_id')->constrained('users');
            $table->unsignedInteger('shipments_count');
            $table->dateTime('scheduled_at');
            $table->unsignedInteger('actual_count')->default(0);
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counted_at')->nullable();
            $table->enum('discrepancy', ['none', 'under', 'over'])->default('none');
            $table->boolean('discrepancy_notified')->default(false);

            $table->enum('status', ['pending', 'assigned', 'in_progress', 'completed', 'canceled'])
                ->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pickup_requests');
    }
};
