<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Observability\IpHasher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Consolidate the two audit implementations (`audits` and `audit_logs`) into
 * the canonical `audits` table, add compliance columns/indexes, hash any legacy
 * raw IP addresses, and drop the duplicate table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audits')) {
            return;
        }

        Schema::table('audits', function (Blueprint $table): void {
            // `morphs()` created these as NOT NULL. System events (failed
            // login, CLI cleanup, consent withdrawal without a model) have no
            // auditable row, so the pair must be nullable.
            $table->string('auditable_type', 191)->nullable()->change();
            $table->unsignedBigInteger('auditable_id')->nullable()->change();

            $table->string('uuid', 26)->nullable()->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('actor_type', 191)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('acted_as_type', 191)->nullable();
            $table->unsignedBigInteger('acted_as_id')->nullable();
            $table->json('changed_columns')->nullable();
            $table->text('reason')->nullable();
            $table->string('request_id', 64)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->string('prev_hash', 64)->nullable();
            $table->string('hash', 64)->nullable();

            $table->index(['tenant_id', 'occurred_at'], 'audits_tenant_occurred_index');
            $table->index(['auditable_type', 'auditable_id', 'occurred_at'], 'audits_auditable_occurred_index');
            $table->index(['actor_type', 'actor_id', 'occurred_at'], 'audits_actor_occurred_index');
            $table->index(['event', 'occurred_at'], 'audits_event_occurred_index');
            $table->index('request_id', 'audits_request_id_index');
        });

        $this->backfillExistingRows();
        $this->copyLegacyAuditLogs();

        Schema::dropIfExists('audit_logs');
    }

    public function down(): void
    {
        if (! Schema::hasTable('audits')) {
            return;
        }

        Schema::table('audits', function (Blueprint $table): void {
            $table->dropUnique(['uuid']);
            $table->dropIndex('audits_tenant_occurred_index');
            $table->dropIndex('audits_auditable_occurred_index');
            $table->dropIndex('audits_actor_occurred_index');
            $table->dropIndex('audits_event_occurred_index');
            $table->dropIndex('audits_request_id_index');
            $table->dropColumn([
                'uuid',
                'tenant_id',
                'actor_type',
                'actor_id',
                'acted_as_type',
                'acted_as_id',
                'changed_columns',
                'reason',
                'request_id',
                'ip_hash',
                'occurred_at',
                'prev_hash',
                'hash',
            ]);
        });

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->morphs('auditable');
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('event', 50);
                $table->string('auditable_type_name')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
                $table->index('user_id');
                $table->index('event');
                $table->index('created_at');
            });
        }
    }

    private function backfillExistingRows(): void
    {
        DB::table('audits')
            ->select(['id', 'user_id', 'created_at', 'ip_address'])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $updates = [
                        'uuid' => (string) Str::ulid(),
                        'occurred_at' => $row->created_at ?? now(),
                        'actor_type' => $row->user_id ? User::class : 'system',
                        'actor_id' => $row->user_id,
                    ];

                    if (! empty($row->ip_address)) {
                        $updates['ip_hash'] = IpHasher::hash($row->ip_address);
                        // Raw addresses must not survive the migration.
                        $updates['ip_address'] = null;
                    }

                    DB::table('audits')->where('id', $row->id)->update($updates);
                }
            });
    }

    private function copyLegacyAuditLogs(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                $inserts = [];

                foreach ($rows as $row) {
                    $inserts[] = [
                        'uuid' => (string) Str::ulid(),
                        'tenant_id' => null,
                        'user_id' => $row->user_id,
                        'actor_type' => $row->user_id ? User::class : 'system',
                        'actor_id' => $row->user_id,
                        'acted_as_type' => null,
                        'acted_as_id' => null,
                        'event' => $row->event,
                        'auditable_type' => $row->auditable_type,
                        'auditable_id' => $row->auditable_id,
                        'auditable_type_name' => $row->auditable_type_name,
                        'meta' => null,
                        'old_values' => $row->old_values,
                        'new_values' => $row->new_values,
                        'changed_columns' => null,
                        'reason' => null,
                        'request_id' => null,
                        'ip_address' => null,
                        'ip_hash' => ! empty($row->ip_address) ? IpHasher::hash($row->ip_address) : null,
                        'user_agent' => $row->user_agent,
                        'description' => $row->description,
                        'occurred_at' => $row->created_at ?? now(),
                        'prev_hash' => null,
                        'hash' => null,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ];
                }

                if ($inserts !== []) {
                    DB::table('audits')->insert($inserts);
                }
            });
    }
};
