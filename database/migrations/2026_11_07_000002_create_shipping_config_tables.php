<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 of the packaging system: reusable box specs, geographic zones and the
 * rate rules that price delivery.
 *
 * Anything the rate engine arithmetic reads is a typed, indexed column — never
 * an EAV attribute — so quote pricing stays a single, index-friendly query.
 * Raw values keep the unit the editor picked; the canonical `*_cm` / `*_kg`
 * columns are recomputed on save and are what the engine uses.
 *
 * `tenant_id` is nullable: a null row is a platform-wide default that every
 * tenant inherits. Tenants may override with their own rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packaging_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();

            // Packaging type is a unit of the `packaging` measurement (box, ctn, plt…).
            $table->foreignId('packaging_type_unit_id')->nullable()->constrained('units')->nullOnDelete();

            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->foreignId('dimension_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('length_cm', 10, 2)->nullable();
            $table->decimal('width_cm', 10, 2)->nullable();
            $table->decimal('height_cm', 10, 2)->nullable();

            // Heaviest payload the box may carry, excluding the box itself.
            $table->decimal('max_weight', 12, 3)->nullable();
            $table->foreignId('weight_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('max_weight_kg', 12, 3)->nullable();

            // Empty-box weight; added to the payload during pick-pack.
            $table->decimal('tare_weight', 12, 3)->nullable();
            $table->foreignId('tare_weight_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('tare_weight_kg', 12, 3)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('shipping_zone_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();

            // Resolution order is specificity: city > postal prefix > state > country.
            $table->string('type', 20)->default('country');
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('state')->nullable();
            $table->string('postal_code_prefix', 10)->nullable();
            $table->timestamps();

            $table->index(['type', 'country_id']);
            $table->index('city_id');
        });

        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            // Null zone = fallback rate that applies wherever no zone matches.
            $table->foreignId('shipping_zone_id')->nullable()->constrained('shipping_zones')->cascadeOnDelete();

            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();

            // `manual` = merchant-defined; carrier codes are added in Phase 4.
            $table->string('carrier', 30)->default('manual');
            // flat | per_weight | tiered
            $table->string('calculation', 20)->default('flat');
            $table->char('currency', 3)->default('PKR');

            $table->decimal('base_amount', 12, 2)->default(0);
            // per_weight: the first N kg are included in base_amount.
            $table->decimal('included_weight_kg', 12, 3)->default(0);
            $table->decimal('per_kg_amount', 12, 2)->default(0);

            // Applicability window. Null means "no bound on this side".
            $table->decimal('min_weight_kg', 12, 3)->nullable();
            $table->decimal('max_weight_kg', 12, 3)->nullable();

            // Subtotal at or above which this option ships free.
            $table->decimal('free_over_amount', 12, 2)->nullable();

            $table->unsignedSmallInteger('estimated_days_min')->nullable();
            $table->unsignedSmallInteger('estimated_days_max')->nullable();

            // Lower priority wins ties and sets the default option order.
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['shipping_zone_id', 'is_active']);
        });

        Schema::create('shipping_rate_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_rate_id')->constrained('shipping_rates')->cascadeOnDelete();
            $table->decimal('min_weight_kg', 12, 3)->default(0);
            $table->decimal('max_weight_kg', 12, 3)->nullable();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->index(['shipping_rate_id', 'min_weight_kg']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rate_tiers');
        Schema::dropIfExists('shipping_rates');
        Schema::dropIfExists('shipping_zone_locations');
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('packaging_templates');
    }
};
