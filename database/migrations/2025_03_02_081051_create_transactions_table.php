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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('from');
            $table->nullableMorphs('to');
            $table->foreignId('shipment_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('type');
            if (!Schema::hasColumn('transactions', 'reference')) {
                $table->string('reference')->nullable();
            }
            if (!Schema::hasColumn('transactions', 'description')) {
                $table->string('description', 1000)->nullable();
            }
            if (!Schema::hasColumn('transactions', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            }

            $table->string('receipt_path')->nullable();
            $table->foreignId('receipt_uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('receipt_uploaded_at')->nullable();
            if (!Schema::hasColumn('transactions', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable();
            }
            if (!Schema::hasColumn('transactions', 'attachment')) {
                $table->string('attachment')->nullable();
            }
            $table->timestamp('settled_at')->nullable()->index();
            $table->unsignedBigInteger('payout_id')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'attachment'))
                $table->dropColumn('attachment');
            if (Schema::hasColumn('transactions', 'warehouse_id'))
                $table->dropColumn('warehouse_id');
        });
    }
};
