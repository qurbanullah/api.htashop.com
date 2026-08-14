<?php

namespace App\Actions\Punchout;

use App\Models\PunchoutSession;
use Carbon\CarbonInterface;

class CleanupPunchoutSessionsAction
{
    public function handle(int $retentionDays, bool $dryRun = false): array
    {
        $now = now();
        $retentionCutoff = $now->copy()->subDays($retentionDays);

        $expirableQuery = PunchoutSession::query()
            ->whereIn('status', ['pending', 'started'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now);

        $expiredCount = (clone $expirableQuery)->count();

        if (!$dryRun && $expiredCount > 0) {
            (clone $expirableQuery)->update([
                'status' => 'expired',
                'last_activity_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $prunableQuery = PunchoutSession::query()
            ->where(function ($query) use ($retentionCutoff) {
                $query->where(function ($completedQuery) use ($retentionCutoff) {
                    $completedQuery->where('status', 'completed')
                        ->whereNotNull('completed_at')
                        ->where('completed_at', '<=', $retentionCutoff);
                })->orWhere(function ($expiredQuery) use ($retentionCutoff) {
                    $expiredQuery->where('status', 'expired')
                        ->where(function ($expiredTimestampQuery) use ($retentionCutoff) {
                            $expiredTimestampQuery->where(function ($expiresAtQuery) use ($retentionCutoff) {
                                $expiresAtQuery->whereNotNull('expires_at')
                                    ->where('expires_at', '<=', $retentionCutoff);
                            })->orWhere(function ($updatedAtFallbackQuery) use ($retentionCutoff) {
                                $updatedAtFallbackQuery->whereNull('expires_at')
                                    ->where('updated_at', '<=', $retentionCutoff);
                            });
                        });
                });
            });

        $prunedCount = (clone $prunableQuery)->count();

        if (!$dryRun && $prunedCount > 0) {
            (clone $prunableQuery)->delete();
        }

        return [
            'expired_count' => $expiredCount,
            'pruned_count' => $prunedCount,
            'dry_run' => $dryRun,
            'retention_days' => $retentionDays,
            'executed_at' => $now->toIso8601String(),
        ];
    }
}
