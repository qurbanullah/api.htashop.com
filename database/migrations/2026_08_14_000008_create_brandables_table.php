<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brandables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->morphs('brandable');
            $table->timestamps();

            $table->unique(['brand_id', 'brandable_id', 'brandable_type'], 'brandable_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brandables');
    }
};
