<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_comments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('post_id')->constrained('forum_posts')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('forum_comments')->cascadeOnDelete();
            $table->text('body');
            $table->string('status')->default('visible');
            $table->unsignedBigInteger('like_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['post_id', 'status', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_comments');
    }
};
