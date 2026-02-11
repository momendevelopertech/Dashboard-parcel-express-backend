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
        Schema::create('merchant_commission_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments', 'id')->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained('users', 'id')->cascadeOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('countries', 'id')->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('states', 'id')->nullOnDelete();
            $table->decimal('base_delivery_fee', 12, 3)->default(0);
            $table->decimal('base_return_fee', 12, 3)->default(0);
            $table->decimal('delivery_discount_amount', 12, 3)->default(0);
            $table->decimal('return_discount_amount', 12, 3)->default(0);
            $table->decimal('delivery_fee', 12, 3);
            $table->decimal('return_fee', 12, 3);
            $table->timestamps();

            $table->index(['shipment_id']);
            $table->index(['merchant_id', 'state_id']);
            $table->unique('shipment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_commission_transactions');
    }
};
