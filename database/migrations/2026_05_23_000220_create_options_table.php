<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('definition_id')->constrained('definitions')->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['definition_id', 'code']);
            $table->index(['definition_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('options');
    }
};
