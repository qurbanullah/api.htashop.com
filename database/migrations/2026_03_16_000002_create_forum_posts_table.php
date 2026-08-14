<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained('forum_topics')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('status')->default('published');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('like_count')->default(0);
            $table->unsignedBigInteger('comment_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['topic_id', 'status', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'is_pinned', 'created_at']);
            $table->index('like_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_posts');
    }
};
