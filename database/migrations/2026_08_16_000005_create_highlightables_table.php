<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('highlightables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('highlight_id')->constrained('highlights')->cascadeOnDelete();
            $table->morphs('highlightable'); // Product / Variant
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('heading_override')->nullable();
            $table->text('body_override')->nullable();
            $table->timestamps();

            $table->unique(['highlight_id', 'highlightable_type', 'highlightable_id'], 'highlightable_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('highlightables');
    }
};
