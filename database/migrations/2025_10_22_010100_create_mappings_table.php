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
        Schema::create('mappings', function (Blueprint $table) {
            $table->id();
            $table->string('app_user_uuid')->index();
            $table->string('app_user_email')->nullable();
            $table->string('app_user_hardware_id')->nullable();
            $table->string('mapping_type')->nullable()->index();
            $table->morphs('mappable');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    Schema::dropIfExists('mappings');
    }
};
