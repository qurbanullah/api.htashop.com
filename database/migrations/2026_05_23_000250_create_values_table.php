<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->morphs('valuable');
            $table->foreignId('definition_id')->constrained('definitions')->cascadeOnDelete();
            $table->foreignId('option_id')->nullable()->constrained('options')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->longText('value_text')->nullable();
            $table->decimal('value_number', 20, 6)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->date('value_date')->nullable();
            $table->timestamp('value_datetime')->nullable();
            $table->json('value_json')->nullable();
            $table->decimal('normalized_number', 20, 6)->nullable();
            $table->string('locale')->nullable();
            $table->string('channel')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'definition_id']);
            $table->index(['locale', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('values');
    }
};
