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
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('type')->default('feedback'); // feedback, feature_request, suggestion, bug_report
            $table->string('name');
            $table->string('email');
            $table->string('subject');
            $table->text('message');
            $table->string('status')->default('new'); // new, read, replied, closed
            $table->string('priority')->default('medium'); // low, medium, high, critical

            // Optional software information
            $table->string('software_name')->nullable();
            $table->string('software_version')->nullable();
            $table->string('operating_system')->nullable();
            $table->json('additional_info')->nullable(); // For any additional metadata

            // Source tracking
            $table->string('source')->default('web'); //web, app. api
            $table->string('user_agent')->nullable();
            $table->ipAddress('ip_address')->nullable();

            // Admin response
            $table->text('admin_response')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->unsignedBigInteger('replied_by')->nullable();

            $table->timestamps();

            // Indexes for better performance
            $table->index(['status', 'created_at']);
            $table->index(['type', 'priority']);
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};
