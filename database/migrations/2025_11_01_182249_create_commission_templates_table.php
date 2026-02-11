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
        Schema::create('commission_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->nullable();
            $table->foreignId('state_id')->nullable();
            $table->decimal('base_delivery_fee', 12, 3)->default(0);
            $table->decimal('base_return_fee', 12, 3)->default(0);
            $table->decimal('delivery_discount_amount', 12, 3)->default(0);
            $table->decimal('return_discount_amount', 12, 3)->default(0);
            $table->decimal('delivery_fee', 12, 3)->default(0);
            $table->decimal('return_fee', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['country_id', 'state_id'], 'commission_templates_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_templates');
    }
};
