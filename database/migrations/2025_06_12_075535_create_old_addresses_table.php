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
        Schema::create('old_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->foreign('shipment_id')->references('id')->on('shipments')->onDelete('cascade');
            $table->index('shipment_id');
            $table->foreignId("consignee_id")->constrained('consignees')->cascadeOnDelete();
            $table->foreignId("country_id")->nullable();
            $table->foreignId("governorate_id")->nullable();
            $table->foreignId("state_id")->nullable();
            $table->foreignId("place_id")->nullable();
            $table->foreignId("city_id")->nullable();
            $table->string("zipcode")->nullable();
            $table->text("streetAddress")->nullable();
            $table->string("longitude")->nullable();
            $table->string("latitude")->nullable();
            $table->text("location")->nullable();
            $table->boolean("approved")->default(false);
            $table->boolean("rejected")->default(false);
            $table->timestamp("approved_at")->nullable();
            $table->foreignId("approved_by")->nullable();
            $table->text("comments")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('old_addresses');
    }
};
