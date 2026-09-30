<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rotating refresh tokens.
     *
     * A refresh token is one-time-use: every refresh issues a successor and marks
     * the presented token rotated. That gives us two properties the access token
     * cannot have:
     *
     *  - a long-lived credential that is only ever sent to one endpoint, and
     *  - theft detection: replaying a token that was already superseded means two
     *    parties hold it, so the whole rotation family is revoked.
     *
     * Only the SHA-256 of the token is stored, so a database read cannot be
     * replayed as a session (same approach as the chat visitor token).
     */
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // SHA-256 hex of the opaque token handed to the client.
            $table->string('token_hash', 64)->unique();

            // Chains every successor of one sign-in, so a detected replay can
            // revoke the lineage instead of just the single token.
            $table->uuid('family_id');

            // Install-scoped id from the native app (see native-app.ts), used to
            // group a device's tokens for auditing and revocation.
            $table->string('device_id', 191)->nullable();

            // The Passport access token issued alongside this refresh token, so
            // rotating also retires the access token it replaces.
            $table->string('access_token_id', 191)->nullable();

            // Successor in the same family; lets us tell "the response was lost"
            // apart from "two parties are using this token".
            $table->unsignedBigInteger('replaced_by_id')->nullable();

            $table->string('revoked_reason', 32)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index('family_id');
            $table->index(['user_id', 'revoked_at']);
            $table->index('expires_at');
            $table->index('replaced_by_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
