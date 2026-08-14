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
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained()->restrictOnDelete();

            // Polymorphic relationship - can translate any model
            $table->morphs('translatable'); // Creates translatable_type & translatable_id

            $table->string('field'); // Field name: 'name', 'description', 'content', etc.
            $table->text('value'); // Translated value

            $table->timestamps();

            // Indexes for performance (morphs already creates translatable_type, translatable_id index)
            $table->index(['language_id', 'translatable_type']);

            // Unique constraint: one translation per language per field per model
            $table->unique([
                'language_id',
                'translatable_type',
                'translatable_id',
                'field'
            ], 'translations_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
