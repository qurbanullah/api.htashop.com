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
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // User who gave consent
            $table->foreignId('user_id')->index()->constrained('users')->cascadeOnDelete();

            // EULA version accepted
            $table->foreignId('eula_id')->index()->constrained('eulas')->cascadeOnDelete();

            // Polymorphic relationship (what was consented for)
            $table->morphs('consentable'); // consentable_type, consentable_id

            // Consent details
            $table->timestamp('accepted_at');
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            // Optional: Context of consent (e.g., which download triggered it)
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'eula_id']);
            $table->index('accepted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
