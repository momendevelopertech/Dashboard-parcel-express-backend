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
        Schema::create('shipment_histories', function (Blueprint $table) {
            $table->id();
            $table->string("fromPkgId")->nullable();
            $table->string("operatorId")->nullable();
            $table->string("name")->nullable();
            $table->text("description")->nullable();
            $table->string("operatorInfo")->nullable();
            $table->string("operationHub")->nullable();
            $table->string("operationHubType")->nullable();
            $table->boolean("trackNode")->nullable();
            $table->string("originActionName")->nullable();
            $table->string("type")->nullable();
            $table->string("time")->nullable();
            $table->bigInteger("shipment_id")->nullable();
            $table->string("proof")->nullable();
            $table->json("data")->nullable();
            $table->boolean("c_show")->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_histories');
    }
};
