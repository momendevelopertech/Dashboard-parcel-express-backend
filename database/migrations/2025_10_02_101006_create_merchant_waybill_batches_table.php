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

        Schema::create('merchant_waybill_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('users', 'id');
            $table->foreignId('created_by')->constrained('users', 'id');
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->index(['merchant_id', 'created_by']);
        });

        Schema::table('merchant_waybills', function (Blueprint $table) {
            $table->foreignId('batch_id')
                ->nullable()
                ->after('merchant_id')
                ->constrained('merchant_waybill_batches', 'id')
                ->nullOnDelete();
            $table->unique('tracking_no'); // اختياري لكن مُفضّل
            $table->index(['merchant_id', 'used']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_waybills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
            $table->dropIndex(['merchant_id', 'used']);
            $table->dropUnique(['tracking_no']);
        });
        Schema::dropIfExists('merchant_waybill_batches');
    }
};
