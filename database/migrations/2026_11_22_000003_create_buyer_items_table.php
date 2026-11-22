<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The buyer's item master, shared by every tenant selling to that buyer.
 *
 * Buyers such as Aramco and Maaden publish two descriptions per item: a short
 * one for lists and a long one for contracts. Both are stored, plus the
 * manufacturer part number and GTIN, so a tenant's item can be suggested
 * automatically during import (see BuyerItemMappingService).
 *
 * `code_key` is the case-insensitive lookup key: upper(trim(code)). The code
 * column keeps the buyer's own casing and leading zeros.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('buyer_party_id')->constrained('buyer_parties')->cascadeOnDelete();

            $table->string('code', 100);
            $table->string('code_key', 100);

            $table->string('short_description', 255)->nullable();
            $table->text('long_description')->nullable();

            $table->string('unit_of_measure', 20)->nullable();
            $table->string('manufacturer_part_number', 100)->nullable();
            $table->string('gtin', 14)->nullable();
            $table->string('unspsc', 20)->nullable();

            $table->boolean('is_active')->default(true);
            $table->string('source', 20)->default('import'); // import|api|manual|backfill
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['buyer_party_id', 'code_key'], 'buyer_items_party_code_unique');
            $table->index(['manufacturer_part_number'], 'buyer_items_mpn_index');
            $table->index(['gtin'], 'buyer_items_gtin_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_items');
    }
};
