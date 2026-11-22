<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links each tenant relationship to the buyer party behind it.
 *
 * Kept nullable so the column can be added before the backfill runs and so a
 * mis-configured relationship never blocks the stack. The application fills it
 * on creation (see EnsureBuyerPartyAction) and the backfill covers old rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('punchout_buyers', function (Blueprint $table): void {
            $table->foreignId('buyer_party_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('buyer_parties')
                ->nullOnDelete();

            $table->index(['buyer_party_id'], 'punchout_buyers_buyer_party_index');
        });
    }

    public function down(): void
    {
        Schema::table('punchout_buyers', function (Blueprint $table): void {
            $table->dropIndex('punchout_buyers_buyer_party_index');
            $table->dropConstrainedForeignId('buyer_party_id');
        });
    }
};
