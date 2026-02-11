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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consignee_id')
                ->constrained('consignees')
                ->cascadeOnDelete();

            // مكوّنات العنوان
            $table->foreignId('country_id')->nullable();
            $table->foreignId('governorate_id')->nullable();
            $table->foreignId('state_id')->nullable();
            $table->foreignId('place_id')->nullable();
            $table->foreignId('city_id')->nullable();
            $table->string('zipcode')->nullable();
            $table->text('streetAddress')->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->text('location_url')->nullable();
            $table->string('label')->nullable(); // "بيت", "مكتب", ...

            // اعتماد/حالة
            $table->boolean('approved')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            // تتبّع الاستخدام
            $table->unsignedInteger('times_used')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('first_approved_shipment_id')->nullable()->constrained('shipments')->nullOnDelete();

            // إدارة
            $table->boolean('is_active')->default(true);
            $table->string('address_signature')->nullable()->index(); // اختياري لمنع التكرار لنفس العميل
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['consignee_id', 'address_signature']); // فعّلها لو هتحسب signature
            $table->index(['consignee_id', 'approved', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
