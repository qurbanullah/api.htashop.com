<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * GDPR cookie-consent audit trail. Unlike the EULA `consents` table
     * (which is user-bound), these records also support anonymous guests
     * identified by a pseudonymous consent token generated client-side.
     */
    public function up(): void
    {
        Schema::create('gdpr_consents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Pseudonymous guest identifier (client-generated, stored in localStorage)
            $table->string('consent_token', 64)->index();

            // Linked account when the visitor is authenticated (nullable for guests)
            $table->foreignId('user_id')->nullable()->index()->constrained('users')->nullOnDelete();

            // Granted categories, e.g. {"necessary":true,"preferences":false,"analytics":true,"marketing":false}
            $table->json('categories');

            // Version of the consent policy the choice was made against
            $table->string('policy_version', 20)->default('1.0');

            // Where the choice was made: banner | settings | account
            $table->string('source', 20)->default('banner');

            // Audit metadata (captured server-side)
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->index(['consent_token', 'accepted_at']);
            $table->index(['user_id', 'accepted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gdpr_consents');
    }
};
