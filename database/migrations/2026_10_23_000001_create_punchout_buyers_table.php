<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('punchout_buyers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // The buyer organization owns the procurement relationship; the
            // contract carries the negotiated price list. Both are optional so
            // a buyer can be onboarded before contracting.
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();

            $table->string('name');
            $table->string('code', 64);
            $table->string('protocol', 20)->nullable();

            // cXML identities. Sender is usually the procurement network;
            // From/To identify the buyer and the seller account.
            $table->string('sender_identity')->nullable();
            $table->string('from_identity')->nullable();
            $table->string('to_identity')->nullable();
            $table->string('shared_secret_hash')->nullable();
            $table->string('previous_shared_secret_hash')->nullable();

            // OCI credentials.
            $table->string('oci_username')->nullable();
            $table->string('oci_password_hash')->nullable();

            $table->timestamp('secret_rotated_at')->nullable();
            $table->json('allowed_return_domains')->nullable();
            $table->string('default_currency', 3)->nullable();
            $table->string('default_locale', 10)->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'protocol', 'is_active']);
            $table->index(['tenant_id', 'organization_id']);
        });

        // The column was added in the session-hardening migration without a
        // foreign key so sessions created before Phase 5 could stay null.
        // SQLite cannot add a constraint to an existing table, so the relation
        // is enforced by the model on both MySQL and SQLite.
    }

    public function down(): void
    {
        Schema::dropIfExists('punchout_buyers');
    }
};
