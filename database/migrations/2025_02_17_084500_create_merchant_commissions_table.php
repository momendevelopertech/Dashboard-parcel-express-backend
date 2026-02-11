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
        Schema::create('merchant_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId("merchant_id");
            $table->foreignId("country_id")->nullable();
            $table->foreignId("state_id")->nullable();
            $table->decimal('base_delivery_fee', 12, 3)->default(0);
            $table->decimal('base_return_fee', 12, 3)->default(0);
            $table->decimal('delivery_discount_amount', 12, 3)->default(0);
            $table->decimal('return_discount_amount', 12, 3)->default(0);
            $table->decimal('delivery_fee', 12, 3);
            $table->decimal('return_fee', 12, 3);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_commissions');
    }
};
