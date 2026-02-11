<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_invoices', function (Blueprint $table) {
            $table->string('invoice_file_path', 191)
                  ->nullable()
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('merchant_invoices', function (Blueprint $table) {
            $table->string('invoice_file_path', 191)
                  ->nullable(false)
                  ->change();
        });
    }
};
