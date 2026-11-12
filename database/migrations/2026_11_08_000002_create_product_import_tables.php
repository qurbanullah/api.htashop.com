<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product file imports are staged: the file is parsed into rows first, then
 * each row is applied. That decouples uploads from catalogue writes, makes
 * large imports resumable, and gives operators a per-row error report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_imports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('format', 10);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('status', 30)->default('pending');
            $table->json('options')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('product_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('product_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('external_id')->nullable();
            $table->json('raw')->nullable();
            $table->json('normalized')->nullable();
            $table->string('status', 20)->default('pending');
            $table->json('errors')->nullable();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->timestamps();

            $table->unique(['import_id', 'row_number']);
            $table->index(['import_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_import_rows');
        Schema::dropIfExists('product_imports');
    }
};
