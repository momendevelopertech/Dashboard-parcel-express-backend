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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('owner');
            $table->string('invoice_no')->nullable();
            $table->morphs('invoiceable');
            $table->foreignId("driver_runsheet_id");
            $table->string("status")->default('pending')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string("payment_voucher")->nullable();
            $table->string("driver_payment_proof")->nullable();
            $table->decimal("paid_to_driver", 10, 2)->nullable();
            $table->text("notes")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
