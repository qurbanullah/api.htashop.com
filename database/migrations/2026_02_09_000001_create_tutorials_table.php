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
        Schema::create('tutorials', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 50)->default('video'); // video, article, course, webinar, guide
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->text('thumbnail')->nullable(); // S3 path for custom thumbnail
            $table->text('video_file')->nullable(); // S3 path for uploaded video file
            $table->text('youtube_url')->nullable(); // YouTube URL
            $table->integer('duration')->nullable()->comment('Duration in seconds');
            $table->string('difficulty_level', 50)->nullable(); // beginner, intermediate, advanced
            $table->string('status', 50)->default('draft'); // draft, published, archived
            $table->integer('views_count')->default(0);
            $table->integer('likes_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->json('metadata')->nullable(); // Additional flexible data
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Indexes for performance
            $table->index(['status', 'published_at']);
            $table->index(['type']);
            $table->index(['created_by']);
            $table->index(['slug']);
            $table->index(['views_count']);
            $table->index(['likes_count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tutorials');
    }
};
