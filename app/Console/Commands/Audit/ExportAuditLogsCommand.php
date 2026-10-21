<?php

declare(strict_types=1);

namespace App\Console\Commands\Audit;

use App\Models\Audit;
use App\Models\AuditExport;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Export one or more days of the audit hot mirror to immutable NDJSON objects.
 *
 * The MariaDB row remains the durable hot record; this command produces the
 * long-term evidence copy and a manifest that chains each day to the previous
 * one. Re-running the same date overwrites the objects idempotently.
 */
class ExportAuditLogsCommand extends Command
{
    protected $signature = 'audit:export
                            {--date= : Export a single UTC date (YYYY-MM-DD)}
                            {--days=1 : Number of days ending yesterday (ignored with --date)}
                            {--dry-run : Report the row count without writing anything}
                            {--force : Re-export even when a completed export exists}';

    protected $description = 'Export audit rows to object storage as daily NDJSON + manifest';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        try {
            $dates = $this->datesToExport();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $failed = false;

        foreach ($dates as $date) {
            try {
                $this->exportDate($date, $dryRun, $force);
            } catch (Throwable $exception) {
                $failed = true;
                report($exception);
                $this->error("{$date->toDateString()}: {$exception->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, Carbon>
     */
    private function datesToExport(): array
    {
        $dateOption = trim((string) $this->option('date'));

        if ($dateOption !== '') {
            $date = Carbon::createFromFormat('Y-m-d', $dateOption, 'UTC');

            if ($date === false || $date->format('Y-m-d') !== $dateOption) {
                throw new \InvalidArgumentException('--date must be a valid YYYY-MM-DD date.');
            }

            return [$date->startOfDay()];
        }

        $days = max(1, (int) $this->option('days'));
        $dates = [];
        $cursor = Carbon::now('UTC')->subDay()->startOfDay();

        for ($i = 0; $i < $days; $i++) {
            $dates[] = $cursor->copy();
            $cursor->subDay();
        }

        return array_reverse($dates);
    }

    private function exportDate(Carbon $date, bool $dryRun, bool $force): void
    {
        $start = $date->copy()->startOfDay();
        $end = $start->copy()->addDay();
        $dateString = $start->toDateString();

        $query = Audit::query()
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end);

        $rows = (clone $query)->count();

        if ($dryRun) {
            $this->line("{$dateString}: {$rows} row(s) would be exported");

            return;
        }

        $existing = AuditExport::query()
            ->where('export_date', $start)
            ->where('status', 'completed')
            ->first();

        if ($existing && ! $force) {
            $this->line("{$dateString}: already exported ({$existing->rows} row(s)); use --force to overwrite");

            return;
        }

        $diskName = (string) config('observability.audit.disk', 'observability');
        $prefix = trim((string) config('observability.audit.prefix', 'audits'), '/');
        $path = "{$prefix}/{$start->format('Y/m/d')}/audit-{$dateString}.ndjson";
        $manifestPath = "{$prefix}/{$start->format('Y/m/d')}/manifest.json";

        $ndjson = '';
        $written = 0;

        $query->select([
            'id',
            'uuid',
            'tenant_id',
            'actor_type',
            'actor_id',
            'acted_as_type',
            'acted_as_id',
            'event',
            'auditable_type',
            'auditable_id',
            'auditable_type_name',
            'old_values',
            'new_values',
            'changed_columns',
            'reason',
            'description',
            'request_id',
            'ip_hash',
            'user_agent',
            'occurred_at',
            'created_at',
            'meta',
        ])->orderBy('id')->chunkById(1000, function ($audits) use (&$ndjson, &$written): void {
            foreach ($audits as $audit) {
                $payload = [
                    'uuid' => $audit->uuid,
                    'tenant_id' => $audit->tenant_id,
                    'actor_type' => $audit->actor_type,
                    'actor_id' => $audit->actor_id,
                    'acted_as_type' => $audit->acted_as_type,
                    'acted_as_id' => $audit->acted_as_id,
                    'event' => $audit->event,
                    'auditable_type' => $audit->auditable_type,
                    'auditable_id' => $audit->auditable_id,
                    'auditable_type_name' => $audit->auditable_type_name,
                    'old_values' => $audit->old_values,
                    'new_values' => $audit->new_values,
                    'changed_columns' => $audit->changed_columns,
                    'reason' => $audit->reason,
                    'description' => $audit->description,
                    'request_id' => $audit->request_id,
                    'ip_hash' => $audit->ip_hash,
                    'user_agent' => $audit->user_agent,
                    'occurred_at' => $audit->occurred_at?->toIso8601String(),
                    'created_at' => $audit->created_at?->toIso8601String(),
                    'meta' => $audit->meta,
                ];

                $line = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                if ($line === false) {
                    throw new \RuntimeException("Could not encode audit row {$audit->uuid}");
                }

                $ndjson .= $line."\n";
                $written++;
            }
        });

        $sha256 = hash('sha256', $ndjson);

        $previous = AuditExport::query()
            ->where('export_date', '<', $start)
            ->orderByDesc('export_date')
            ->first();

        $manifest = [
            'date' => $dateString,
            'path' => $path,
            'rows' => $written,
            'sha256' => $sha256,
            'generated_at' => now()->toIso8601String(),
            'exporter_version' => '1',
            'prev_manifest_hash' => $previous?->manifest_hash,
        ];

        $manifestHash = $this->manifestHash($manifest);
        $manifest['manifest_hash'] = $manifestHash;

        $disk = Storage::disk($diskName);

        $disk->put($path, $ndjson);
        $disk->put($manifestPath, json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n");

        AuditExport::query()->updateOrCreate(
            ['export_date' => $start],
            [
                'disk' => $diskName,
                'path' => $path,
                'manifest_path' => $manifestPath,
                'rows' => $written,
                'sha256' => $sha256,
                'manifest_hash' => $manifestHash,
                'prev_manifest_hash' => $previous?->manifest_hash,
                'status' => 'completed',
                'completed_at' => now(),
            ],
        );

        $this->info("{$dateString}: exported {$written} row(s) to {$path}");
    }

    /**
     * Hash the manifest without its own hash, chained to the previous day.
     *
     * @param  array<string, mixed>  $manifest
     */
    private function manifestHash(array $manifest): string
    {
        unset($manifest['manifest_hash'], $manifest['generated_at']);

        return hash(
            'sha256',
            json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'|'.($manifest['prev_manifest_hash'] ?? ''),
        );
    }
}
