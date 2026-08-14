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
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable'); // commentable_type, commentable_id (Reviewer, Manuscript, etc.)
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();

            $table->text('content');
            $table->boolean('is_internal')->default(false); // visible only to editors/reviewers
            $table->boolean('is_read')->default(false);

            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            // $table->index(['commentable_type', 'commentable_id']);
            $table->index('user_id');
            $table->index('parent_id');
            $table->index(['commentable_type', 'commentable_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
