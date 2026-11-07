<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_shipping', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();

            // Weights are stored in the unit the editor picked; the *_kg column
            // is the normalised value used by the rate engine.
            $table->decimal('net_weight', 12, 3)->nullable();
            $table->decimal('gross_weight', 12, 3)->nullable();
            $table->foreignId('weight_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('gross_weight_kg', 12, 3)->nullable()->index();

            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->foreignId('dimension_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('length_cm', 10, 2)->nullable()->index();
            $table->decimal('width_cm', 10, 2)->nullable();
            $table->decimal('height_cm', 10, 2)->nullable();

            // Packaging type is a unit of the `packaging` measurement (box, ctn, plt…).
            $table->foreignId('packaging_type_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->unsignedInteger('units_per_case')->default(1);

            $table->boolean('ships_alone')->default(false);
            $table->boolean('is_fragile')->default(false);
            $table->boolean('is_stackable')->default(true);
            $table->boolean('is_hazmat')->default(false);

            // Domestic hazmat only: UN number plus the standard class/group lists.
            $table->string('un_number', 20)->nullable();
            $table->foreignId('hazard_class_option_id')->nullable()->constrained('options')->nullOnDelete();
            $table->foreignId('packing_group_option_id')->nullable()->constrained('options')->nullOnDelete();

            $table->text('handling_notes')->nullable();
            $table->json('contents')->nullable();

            $table->timestamps();
        });

        Schema::create('variant_shipping', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->unique()->constrained('variants')->cascadeOnDelete();

            $table->decimal('net_weight', 12, 3)->nullable();
            $table->decimal('gross_weight', 12, 3)->nullable();
            $table->foreignId('weight_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('gross_weight_kg', 12, 3)->nullable()->index();

            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->foreignId('dimension_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('length_cm', 10, 2)->nullable();
            $table->decimal('width_cm', 10, 2)->nullable();
            $table->decimal('height_cm', 10, 2)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_shipping');
        Schema::dropIfExists('product_shipping');
    }
};
