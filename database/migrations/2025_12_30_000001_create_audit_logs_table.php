<?php

declare(strict_types=1);

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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Polymorphic relation to the audited model
            $table->morphs('auditable');

            // User who performed the action
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Event type: created, updated, deleted, etc.
            $table->string('event', 50);

            // Model class name for quick reference
            $table->string('auditable_type_name')->nullable();

            // Old values before change (JSON)
            $table->json('old_values')->nullable();

            // New values after change (JSON)
            $table->json('new_values')->nullable();

            // IP address of the user
            $table->string('ip_address', 45)->nullable();

            // User agent
            $table->text('user_agent')->nullable();

            // Optional description/notes
            $table->text('description')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index('user_id');
            $table->index('event');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
