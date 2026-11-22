<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The buyer's own identity, shared by every tenant that sells to that buyer.
 *
 * `punchout_buyers` is the tenant relationship (credentials, contract,
 * settings). This table is the party behind it, so two tenants onboarding the
 * same buyer can point at one record and share one item master.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_parties', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');

            // Ariba ANID / DUNS / customer key. Null while the party is a
            // private placeholder that has not been linked to a real buyer.
            $table->string('network_id', 64)->nullable()->unique();
            $table->string('duns', 32)->nullable();
            $table->string('country', 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_parties');
    }
};
