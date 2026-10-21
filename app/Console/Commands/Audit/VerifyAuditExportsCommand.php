<?php

declare(strict_types=1);

namespace App\Console\Commands\Audit;

use App\Models\AuditExport;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Verify the exported audit evidence.
 *
 * Checks each NDJSON object against its manifest (row count + SHA-256) and,
 * when verifying all exports, checks that each manifest chains to the previous
 * day. A failure means the archive or the database bookkeeping was modified.
 */
class VerifyAuditExportsCommand extends Command
{
    protected $signature = 'audit:verify
                            {--date= : Verify a single YYYY-MM-DD export}
                            {--days=0 : Verify the last N exports (0 = all)}';

    protected $description = 'Verify audit NDJSON exports against their manifests and hash chain';

    public function handle(): int
    {
        $date = trim((string) $this->option('date'));
        $days = max(0, (int) $this->option('days'));

        $query = AuditExport::query()
            ->where('status', 'completed')
            ->orderBy('export_date');

        if ($date !== '') {
            try {
                $dateValue = Carbon::createFromFormat('Y-m-d', $date, 'UTC');
            } catch (\Throwable) {
                $dateValue = null;
            }

            if (! $dateValue instanceof Carbon || $dateValue->format('Y-m-d') !== $date) {
                $this->error('--date must be a valid YYYY-MM-DD date.');

                return self::FAILURE;
            }

            $query->where('export_date', $dateValue->startOfDay());
        }

        $exports = $query->get();

        if ($days > 0) {
            $exports = $exports
                ->sortByDesc('export_date')
                ->take($days)
                ->sortBy('export_date')
                ->values();
        }

        if ($exports->isEmpty()) {
            $this->warn('No completed audit exports found.');

            return self::SUCCESS;
        }

        $failures = 0;
        $previousManifestHash = null;
        $checkChain = $date === '';

        foreach ($exports as $export) {
            $disk = Storage::disk($export->disk);

            if (! $disk->exists($export->path) || ! $disk->exists($export->manifest_path)) {
                $this->error("{$export->export_date->toDateString()}: NDJSON or manifest is missing");
                $failures++;

                continue;
            }

            $ndjson = $disk->get($export->path);
            $manifest = json_decode($disk->get($export->manifest_path), true);

            if (! is_array($manifest)) {
                $this->error("{$export->export_date->toDateString()}: manifest is not valid JSON");
                $failures++;

                continue;
            }

            $rows = substr_count($ndjson, "\n");
            $sha256 = hash('sha256', $ndjson);
            $manifestForHash = $manifest;
            unset($manifestForHash['manifest_hash'], $manifestForHash['generated_at']);
            $expectedManifestHash = hash(
                'sha256',
                json_encode($manifestForHash, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'|'.($manifest['prev_manifest_hash'] ?? ''),
            );

            $problems = [];

            if ($rows !== (int) $export->rows) {
                $problems[] = "row count {$rows} != {$export->rows}";
            }

            if ($sha256 !== $export->sha256) {
                $problems[] = 'NDJSON sha256 differs from the database record';
            }

            if (($manifest['sha256'] ?? null) !== $sha256) {
                $problems[] = 'NDJSON sha256 differs from the manifest';
            }

            if (($manifest['manifest_hash'] ?? null) !== $expectedManifestHash) {
                $problems[] = 'manifest hash is invalid';
            }

            if ($checkChain && $previousManifestHash !== null && ($manifest['prev_manifest_hash'] ?? null) !== $previousManifestHash) {
                $problems[] = 'manifest does not chain to the previous export';
            }

            if ($problems !== []) {
                $failures++;
                $this->error("{$export->export_date->toDateString()}: ".implode('; ', $problems));
            } else {
                $this->line("{$export->export_date->toDateString()}: OK ({$rows} row(s))");
            }

            $previousManifestHash = $manifest['manifest_hash'] ?? null;
        }

        if ($failures > 0) {
            $this->error("{$failures} export(s) failed verification.");

            return self::FAILURE;
        }

        $this->info('All audit exports verified.');

        return self::SUCCESS;
    }
}
