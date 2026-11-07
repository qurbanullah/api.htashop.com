<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5: origins, hazmat capability and per-box contents.
 *
 * - A rate may be scoped to a warehouse (origin); null means any origin.
 * - `carries_hazmat` lets a merchant say a service cannot take dangerous goods.
 * - `parcel_items` records which order lines are in which box, which is what
 *   makes the packing plan and hazmat segregation real rather than suggested.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_rates', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('shipping_zone_id')->constrained('warehouses')->nullOnDelete();
            $table->boolean('carries_hazmat')->default(true)->after('is_active');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('tenant_id')->constrained('warehouses')->nullOnDelete();
        });

        Schema::table('parcels', function (Blueprint $table) {
            $table->boolean('contains_hazmat')->default(false)->after('weight_kg');
        });

        Schema::create('parcel_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->constrained('parcels')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->timestamps();

            $table->unique(['parcel_id', 'order_item_id']);
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_items');

        Schema::table('parcels', function (Blueprint $table) {
            $table->dropColumn('contains_hazmat');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::table('shipping_rates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn('carries_hazmat');
        });
    }
};
