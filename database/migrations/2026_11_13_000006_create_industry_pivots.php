<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which industries a brand, manufacturer or product category belongs to.
 *
 * Categories use this for merchandising ("industries we serve"); brands and
 * manufacturers use it to classify the supplier catalogue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_industry', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('industry_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['brand_id', 'industry_id']);
            $table->index(['industry_id', 'brand_id']);
        });

        Schema::create('manufacturer_industry', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manufacturer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('industry_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['manufacturer_id', 'industry_id']);
            $table->index(['industry_id', 'manufacturer_id']);
        });

        Schema::create('category_industry', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('industry_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['category_id', 'industry_id']);
            $table->index(['industry_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_industry');
        Schema::dropIfExists('manufacturer_industry');
        Schema::dropIfExists('brand_industry');
    }
};
