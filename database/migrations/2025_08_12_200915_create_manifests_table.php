<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('manifests', function (Blueprint $table) {
            $table->id();
            $table->string('manifest_serial')->unique();
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('total_shipments');
            $table->decimal('total_weight', 10, 2);
            $table->decimal('total_value', 10, 2);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('manifests');
    }
};
