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
        Schema::create('shipment_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId("shipment_id")->constrained();
            $table->integer("ofd_count")->default(0)->nullable();
            $table->string("status")->nullable()->default("NOT_DELIVERED");
            $table->string("driver_call_count")->nullable()->default(0);
            $table->date("future_delivery_date")->nullable();
            $table->timestamp('deliver_later_until')->nullable()->index();
            $table->enum('deliver_later_reason', ['DELIVER_LATER_TODAY'])->nullable();
            $table->string("payment_bank_transfer")->nullable();
            $table->string("payment_cash")->nullable();
            $table->string("proof")->nullable();
            $table->double("delivery_lat", 14, 10)->nullable();
            $table->double("delivery_lng", 14, 10)->nullable();
            $table->text("note")->nullable();

            $table->string("correct_address_link")->nullable();

            $table->integer("delivery_otp")->nullable();
            $table->timestamp("otp_generated_at")->nullable();
            $table->integer("otp_attempts")->default(0);
            $table->json("otp_verified_address_tokens")->nullable();
            $table->boolean("otp_verified")->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_deliveries');
    }
};
