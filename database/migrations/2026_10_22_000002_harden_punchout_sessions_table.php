<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('punchout_sessions', function (Blueprint $table): void {
            // The legacy `token` column stays for one release so existing buyer
            // configurations keep working. New sessions also store a hash and
            // are resolved with hash_equals.
            $table->string('token_hash', 64)->nullable()->unique()->after('token');
            $table->string('access_token_hash', 64)->nullable()->unique()->after('token_hash');
            $table->string('handoff_code_hash', 64)->nullable()->unique()->after('access_token_hash');
            $table->timestamp('handoff_code_expires_at')->nullable()->after('handoff_code_hash');
            $table->timestamp('handoff_code_used_at')->nullable()->after('handoff_code_expires_at');
            $table->timestamp('idle_expires_at')->nullable()->after('expires_at');
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');

            // The buyers table lands in Phase 5. Keep the column now so sessions
            // created before then upgrade without a second migration.
            $table->unsignedBigInteger('buyer_id')->nullable()->after('tenant_id');
            $table->index('buyer_id');

            $table->index(['tenant_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('punchout_sessions', function (Blueprint $table): void {
            $table->dropUnique(['token_hash']);
            $table->dropUnique(['access_token_hash']);
            $table->dropUnique(['handoff_code_hash']);
            $table->dropIndex(['buyer_id']);
            $table->dropIndex(['tenant_id', 'status', 'expires_at']);
            $table->dropColumn([
                'token_hash',
                'access_token_hash',
                'handoff_code_hash',
                'handoff_code_expires_at',
                'handoff_code_used_at',
                'idle_expires_at',
                'cancelled_at',
                'buyer_id',
            ]);
        });
    }
};
