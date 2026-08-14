<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Polymorphic Many-to-Many pivot table for comment contexts.
     * Allows comments to be associated with multiple entities (Revisions, Authors, Sections, etc.)
     * while maintaining primary ownership through comments.commentable_type/id
     */
    public function up(): void
    {
        Schema::create('commentables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->morphs('commentable'); // Creates commentable_type and commentable_id with automatic index
            $table->string('context_type')->default('revision'); // revision, author, section, figure, etc.
            $table->json('metadata')->nullable(); // For future flexibility
            $table->timestamps();

            // Unique constraint: prevent duplicate associations
            $table->unique(['comment_id', 'commentable_id', 'commentable_type'], 'commentable_unique');

            // Additional indexes for efficient queries (morphs() already creates one index)
            $table->index(['comment_id', 'context_type']);
            $table->index(['context_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commentables');
    }
};
