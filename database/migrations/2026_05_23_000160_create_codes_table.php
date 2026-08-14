<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->morphs('codeable');
            $table->string('type');
            $table->string('value');
            $table->string('normalized');
            $table->string('context')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'normalized']);
            $table->index(['organization_id', 'type', 'normalized']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codes');
    }
};
