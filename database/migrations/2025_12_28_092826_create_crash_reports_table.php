<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('crash_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Machine identification
            $table->string('machine_id')->nullable()->index();
            $table->string('hostname')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('platform')->nullable(); // e.g., 'Windows', 'macOS', 'Linux'
            $table->string('os_version')->nullable();
            $table->string('app_version')->nullable();
            $table->string('app_name')->nullable()->index();

            // Crash details
            $table->text('error_message')->nullable();
            $table->text('stack_trace')->nullable();
            $table->string('crash_type')->nullable(); // e.g., 'exception', 'segfault', 'memory'
            $table->string('severity')->default('medium'); // 'low', 'medium', 'high', 'critical'

            // File information
            $table->string('file_path')->nullable(); // Path to stored crash dump file
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->default(0); // Size in bytes
            $table->string('file_hash')->nullable(); // SHA256 hash for deduplication

            // Status and metadata
            $table->string('status')->default('new'); // 'new', 'investigating', 'resolved', 'ignored'
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();

            // Analytics
            $table->json('metadata')->nullable(); // Additional context (memory usage, CPU, etc.)
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->timestamp('last_occurred_at')->nullable();

            // Assignment
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('status');
            $table->index('severity');
            $table->index('crash_type');
            $table->index('is_resolved');
            $table->index('created_at');
            $table->index('file_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crash_reports');
    }
};
