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
        Schema::table('merchant_address_books', function (Blueprint $table) {
               $table->string("country_key_cellphone")->nullable();
                $table->string("country_key_alternatePhone")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_address_books', function (Blueprint $table) {
            //
        });
    }
};
