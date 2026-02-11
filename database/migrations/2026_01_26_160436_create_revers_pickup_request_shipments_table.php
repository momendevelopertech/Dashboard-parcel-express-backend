<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reverse_pickup_request_shipments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('reverse_pickup_request_id');

            $table->foreign('reverse_pickup_request_id', 'rprs_request_fk')
                ->references('id')
                ->on('reverse_pickup_requests')
                ->onDelete('cascade');

            $table->json('reverse_shipment_ids');
            $table->dateTime('want_receive_at')->nullable();
            $table->boolean('is_hub_receive')->default(false);

            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('reverse_pickup_request_shipments');
    }
};
