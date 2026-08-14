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
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Email metadata
            $table->string('mailable_type')->index(); // Class name of the mailable
            $table->string('recipient_email')->index();
            $table->string('recipient_name')->nullable();
            $table->string('subject');

            // Status tracking
            $table->enum('status', ['pending', 'sent', 'failed', 'bounced'])->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();

            // Context information
            $table->string('context_type')->nullable()->index(); // e.g., 'license', 'contact_message', 'ticket'
            $table->unsignedBigInteger('context_id')->nullable()->index();
            $table->json('context_data')->nullable(); // Additional context data

            // User tracking
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Metadata
            $table->json('metadata')->nullable(); // Additional metadata (headers, attachments info, etc.)
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->integer('retry_count')->default(0);

            $table->timestamps();

            // Indexes for common queries
            $table->index(['context_type', 'context_id']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
