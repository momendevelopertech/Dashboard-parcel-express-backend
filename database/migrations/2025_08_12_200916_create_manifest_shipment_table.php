<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('manifest_shipment', function (Blueprint $table) {
            $table->foreignId('manifest_id')->constrained('manifests')->onDelete('cascade');
            $table->foreignId('shipment_id')->constrained('shipments')->onDelete('cascade');
            $table->primary(['manifest_id', 'shipment_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('manifest_shipment');
    }
};
