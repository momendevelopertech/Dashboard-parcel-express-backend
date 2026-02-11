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
        Schema::create('transfer_fees', function (Blueprint $table) {
            $table->id();
            $table->string('warehouse');
            $table->decimal('amount', 10, 3)->default(0);
            $table->timestamps();

            $table->unique('warehouse');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_fees');
    }
};
