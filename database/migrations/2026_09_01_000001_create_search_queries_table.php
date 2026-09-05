<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_queries', function (Blueprint $table) {
            $table->id();
            $table->string('query');
            $table->string('normalized_query')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable()->index();
            $table->string('source')->default('results')->index(); // suggest | results
            $table->unsignedInteger('results_count')->default(0);
            $table->boolean('is_zero_result')->default(false)->index();
            $table->json('filters')->nullable();
            $table->unsignedBigInteger('clicked_product_id')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();

            $table->index('query');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_queries');
    }
};
