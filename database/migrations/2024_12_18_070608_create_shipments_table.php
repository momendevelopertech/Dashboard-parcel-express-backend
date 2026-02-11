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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs("facility");
            $table->nullableMorphs("owner");
            $table->foreignId("consignee_id")->nullable()->constrained('consignees', 'id')->nullOnDelete();
            $table->foreignId("customer_id")->nullable()->constrained('customers', 'id')->nullOnDelete();
            $table->foreignId("shipper_id")->nullable()->constrained('shippers', 'id')->nullOnDelete();
            $table->foreignId("merchant_id")->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->foreignId("driver_id")->nullable();
            $table->foreignId("shipment_type_id")->nullable()->default(1);
            $table->foreignId("assignment_id")->nullable();
            $table->string('pre_id')->nullable()->unique();
            $table->string("tracking_no")->nullable()->unique()->index();
            $table->foreignId("from_hub_id")->nullable();
            $table->foreignId("current_hub_id")->nullable();
            $table->foreignId("target_hub_id")->nullable();
            $table->foreignId("final_hub_id")->nullable();
            $table->decimal("value", 10, 2)->nullable();
            $table->decimal("total_cod", 10, 2)->nullable();
            $table->decimal("delivery_fee", 10, 2)->nullable();
            $table->string("payment_type")->nullable();

            $table->text("pickup_address")->nullable();
            $table->boolean("is_return")->default(false);
            $table->boolean("in_exception")->default(false); // when exception happens 
            $table->boolean("is_sorted")->default(false); // when exception happens 
            // $table->boolean("is_delivered")->default(false);
            $table->integer("created_by")->nullable();
            $table->text("notes")->nullable();
            $table->string("status")->default('CREATED');

            $table->boolean("allow_return")->default(true);
            $table->string("delivery_priority")->default('normal');
            $table->string("delivery_time")->default('any');
            $table->string("sender_district")->nullable();
            $table->string("sender_location_url")->nullable();
            $table->decimal('sender_longitude', 10, 7)->nullable();
            $table->decimal('sender_latitude', 10, 7)->nullable();
            $table->text("sender_notes")->nullable();
            $table->string("sender_streetAddress")->nullable();
            $table->string("sender_zipcode")->nullable();
            $table->foreignId("sender_country_id")->nullable();
            $table->foreignId("sender_governorate_id")->nullable();
            $table->foreignId("sender_state_id")->nullable();
            $table->foreignId("sender_place_id")->nullable();
            $table->boolean("need_invoice")->default(false);
            $table->foreignId("country_id")->nullable();
            $table->foreignId("governorate_id")->nullable();
            $table->foreignId("state_id")->nullable();
            $table->foreignId("place_id")->nullable();
            $table->foreignId("city_id")->nullable();

            $table->string('zipcode')->nullable();
            $table->string('streetAddress')->nullable();

            $table->string("location_url")->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();

            $table->boolean("is_walkin")->default(false);
            $table->string("customer_name")->nullable();
            $table->string("customer_phone")->nullable();
            $table->string("customer_id_card")->nullable();
            $table->string("fee_payer")->nullable();
            $table->boolean("is_outsourced")->default(false);

            $table->timestamps();
            $table->index(['pre_id', 'tracking_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
