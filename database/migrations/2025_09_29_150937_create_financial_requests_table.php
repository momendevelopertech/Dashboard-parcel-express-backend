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
        Schema::create('financial_requests', function (Blueprint $table) {
            $table->id();


            $table->unsignedBigInteger('owner_id')->index();
            $table->string('owner_type')->index();

            $table->string('code')->unique();
            $table->enum('type', [
                'merchant_settlement',
                'driver_salary',
                'branch_settlement',
                'other'
            ])->index();

            $table->string('payee_id')->nullable();
            $table->string('period')->nullable();
            $table->decimal('amount', 12, 3)->default(0);
            $table->text('notes')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('attachment_path')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();

            $table->date('period_date')->nullable();
            $table->string('payee_name')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_requests');
    }
};
