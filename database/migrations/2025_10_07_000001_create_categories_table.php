<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->onDelete('cascade');
            $table->integer('level')->default(0)->comment('Hierarchy level: 0=root, 1=child, etc.');
            $table->string('path')->nullable()->comment('Path from root: /1/2/3');
            $table->string('image')->nullable();
            $table->string('icon')->nullable();
            $table->string('color', 7)->nullable()->comment('Hex color code');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['slug', 'is_active']);
            $table->index('parent_id');
            $table->index('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
