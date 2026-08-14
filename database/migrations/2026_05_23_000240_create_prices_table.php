<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('catalog_id')->nullable()->constrained('catalogs')->nullOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->morphs('priceable');
            $table->string('type')->default('fixed');
            $table->decimal('amount', 20, 6);
            $table->decimal('min_quantity', 20, 6)->default(1);
            $table->decimal('max_quantity', 20, 6)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'organization_id']);
            $table->index(['contract_id', 'catalog_id']);
            $table->index(['currency_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
