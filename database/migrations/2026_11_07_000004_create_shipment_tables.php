<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: fulfilment.
 *
 * An order may be fulfilled by several shipments (partial dispatch). Each
 * shipment says which order items it covers (`shipment_items`) and is packed
 * into one or more boxes (`parcels`), which is what a carrier collects.
 *
 * Item quantities live on `shipment_items` rather than on `parcels`: choosing
 * which unit goes into which box is the bin-packing problem planned for Phase 5,
 * while operations today only need "how much is dispatched, in how many boxes".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            $table->string('status', 20)->default('pending');
            $table->string('carrier', 30)->default('manual');
            $table->string('service', 80)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('tracking_url', 255)->nullable();

            // Derived from the parcels on save; kept denormalised for list views.
            $table->decimal('weight_kg', 12, 3)->nullable();

            // Address snapshots: a later edit in the address book must not
            // rewrite where a dispatched parcel actually went.
            $table->json('ship_from')->nullable();
            $table->json('ship_to')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('packed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('tracking_number');
            $table->index('order_id');
        });

        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            // Matches `order_items.quantity` (decimal:3) so partial units work.
            $table->decimal('quantity', 12, 3);
            $table->timestamps();

            $table->unique(['shipment_id', 'order_item_id']);
            $table->index('order_item_id');
        });

        Schema::create('parcels', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('packaging_template_id')->nullable()->constrained('packaging_templates')->nullOnDelete();

            // Box number within the shipment: 1 of 3, 2 of 3, …
            $table->unsignedInteger('sequence')->default(1);

            $table->decimal('length_cm', 10, 2)->nullable();
            $table->decimal('width_cm', 10, 2)->nullable();
            $table->decimal('height_cm', 10, 2)->nullable();
            $table->decimal('weight_kg', 12, 3)->nullable();

            $table->string('tracking_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['shipment_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcels');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
    }
};
