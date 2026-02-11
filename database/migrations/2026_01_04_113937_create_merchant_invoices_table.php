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
        Schema::create('merchant_invoices', function (Blueprint $table) {
            $table->id();
            $table->string("invoice_file_path");
            $table->decimal('amount', 18, 2);
            $table->unsignedBigInteger("merchant_id");
            $table->foreign("merchant_id")->references("id")->on("users");
            $table->unsignedBigInteger("created_by");
            $table->foreign("created_by")->references("id")->on("users");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_invoices');
    }
};
