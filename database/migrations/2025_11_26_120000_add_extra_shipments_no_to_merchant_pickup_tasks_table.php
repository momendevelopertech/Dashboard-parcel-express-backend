<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_pickup_tasks', function (Blueprint $table) {
            $table->unsignedInteger('extra_shipments_no')->default(0)->after('picked_shipments_no');
        });
    }

    public function down(): void
    {
        Schema::table('merchant_pickup_tasks', function (Blueprint $table) {
            $table->dropColumn('extra_shipments_no');
        });
    }
};

