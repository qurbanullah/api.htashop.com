<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Polymorphic pivot table for categories
        Schema::create('categorizables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->index();
            $table->morphs('categorizable');
            $table->timestamps();

            $table->unique(['category_id', 'categorizable_id', 'categorizable_type'], 'categorizable_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorizables');
    }
};
