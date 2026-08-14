<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('status')->default('draft');
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->json('configuration')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_id', 'slug']);
            $table->index(['product_id', 'status', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variants');
    }
};
