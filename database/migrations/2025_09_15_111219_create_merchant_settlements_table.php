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
        Schema::create('merchant_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('users')->cascadeOnDelete();
            $table->string('reference')->nullable()->index();
            $table->decimal('amount', 18, 2);
            $table->string('status', 20)->default('posted');
            $table->text('notes')->nullable();
            $table->string('receipt_path')->nullable();
            $table->foreignId('receipt_uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('receipt_uploaded_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_settlements');
    }
};
