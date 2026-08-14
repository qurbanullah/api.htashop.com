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
        Schema::create('eulas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Version and identification
            $table->string('version', 50)->index();

            // Content
            $table->string('title');
            $table->longText('content'); // HTML content from TrixEditor

            // Status and dates
            $table->string('status', 20)->default('draft')->index(); // draft, active, inactive
            $table->date('effective_date')->nullable();

            // Metadata
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eulas');
    }
};
