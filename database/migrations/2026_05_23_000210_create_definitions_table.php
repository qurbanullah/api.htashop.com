<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('definitions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('definitions')->nullOnDelete();
            $table->foreignId('measurement_id')->nullable()->constrained('measurements')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('kind')->default('attribute');
            $table->string('value_type')->default('text');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_searchable')->default(false);
            $table->boolean('is_multi')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('validation')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'kind', 'value_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('definitions');
    }
};
