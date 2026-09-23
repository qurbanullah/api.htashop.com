<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // The knowledge base is global for HTAShop. The column is nullable
            // so a single tenant's private entries can be added later without
            // a schema rewrite; a null tenant_id means "everyone".
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            // `*` = applies to every locale. Otherwise an ISO code (en, de, ur).
            $table->string('locale', 8)->default('*');
            $table->string('title');
            $table->string('slug')->nullable();
            // Canonical question, improves keyword matching for FAQ-style entries.
            $table->string('question')->nullable();
            $table->longText('body');

            $table->string('source_type')->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_url', 2048)->nullable();

            $table->json('tags')->nullable();
            $table->string('status')->default('draft');
            // Never answer from model memory — link the source or escalate.
            $table->boolean('restricted')->default(false);
            // Higher priority passages are surfaced first when scores tie.
            $table->integer('priority')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'locale']);
            $table->index(['tenant_id', 'status']);
            $table->index(['source_type', 'source_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_entries');
    }
};
