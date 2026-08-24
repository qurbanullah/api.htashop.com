<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->text('body')->nullable();
            $table->string('image_key')->nullable(); // E2 object key (desktop)
            $table->string('mobile_image_key')->nullable(); // E2 object key (mobile)
            $table->string('link_type')->default('none'); // none | product | category | brand | search | external
            $table->string('link_value')->nullable();
            $table->string('type')->default('single'); // hero | promo | sponsored | top_brands | just_launched | split | single
            $table->string('placement')->default('home'); // home | category | search | product_detail
            $table->json('category_ids')->nullable();
            $table->json('brand_ids')->nullable();
            $table->json('product_ids')->nullable();
            $table->json('search_keywords')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['placement', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
