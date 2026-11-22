<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-scoped mapping: "this buyer item is my product".
 *
 * This is the only part of a buyer code that is naturally per tenant, and it is
 * deliberately thin. One buyer item maps to at most one of a tenant's own
 * items, which is enforced by the unique index; a conflict means someone tried
 * to map the same code to a second product and must use an explicit remap.
 *
 * `tenant_id` leads every index because it is the isolation boundary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_item_mappings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Denormalised from the item so lookups stay tenant-first.
            $table->foreignId('buyer_party_id')->constrained('buyer_parties')->cascadeOnDelete();
            $table->foreignId('buyer_item_id')->constrained('buyer_items')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('variants')->cascadeOnDelete();

            $table->boolean('is_primary')->default(true);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('source', 20)->default('manual'); // manual|import|api|backfill
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'buyer_item_id'], 'buyer_item_mappings_tenant_item_unique');
            $table->index(['tenant_id', 'product_id'], 'buyer_item_mappings_by_product');
            $table->index(['tenant_id', 'buyer_party_id'], 'buyer_item_mappings_by_party');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_item_mappings');
    }
};
