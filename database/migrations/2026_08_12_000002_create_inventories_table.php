<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->morphs('stockable'); // stockable_type + stockable_id (Product | Variant)
            $table->string('sku')->nullable();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('reserved', 15, 3)->default(0);
            $table->decimal('low_stock_threshold', 15, 3)->nullable();
            $table->boolean('track_inventory')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['warehouse_id', 'stockable_type', 'stockable_id']);
            $table->index(['tenant_id', 'warehouse_id']);
            $table->index(['sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
