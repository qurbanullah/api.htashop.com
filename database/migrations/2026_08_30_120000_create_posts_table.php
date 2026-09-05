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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 50)->default('post');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->text('featured_image')->nullable();
            $table->json('images')->nullable(); // Array of image URLs
            $table->enum('status', ['draft', 'scheduled', 'published', 'sending', 'sent'])->default('draft');
            $table->boolean('is_published_as_blog')->default(false);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('recipients')->nullable(); // Store recipient criteria
            $table->integer('recipients_count')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('opened_count')->default(0);
            $table->integer('clicked_count')->default(0);
            $table->json('metadata')->nullable();

            // Nullable polymorphic relation — a post may belong to a product,
            // tenant/organization ("global"), etc., so posts can be shown on
            // product detail pages or other entity pages.
            $table->nullableMorphs('postable');

            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('primary_category_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['is_published_as_blog']);
            $table->index(['created_by']);
            $table->index(['type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
