<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_response_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('part_number')->nullable();
            $table->text('description')->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['quote_response_id', 'sort_order']);
        });

        Schema::create('quote_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['quote_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_activities');
        Schema::dropIfExists('quote_response_items');
    }
};
