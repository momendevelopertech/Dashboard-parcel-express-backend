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
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->enum('platform', ['android', 'ios']);    
            $table->string('app_code')->default('default');  
            $table->string('min_supported_version');   
            $table->unsignedInteger('min_supported_build')->default(0);
            $table->string('latest_version');              
            $table->unsignedInteger('latest_build')->default(0); 
            $table->boolean('force_all')->default(false);
            $table->string('store_url')->nullable(); 
            $table->text('changelog')->nullable();  
            $table->timestamps();

            $table->unique(['platform', 'app_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
