<?php

declare(strict_types=1);

namespace App\Contracts\Analytics;

/**
 * Write-behind buffer for analytics counters.
 *
 * The request/job path only increments counters here; the scheduled flush is
 * the only code that writes analytics rows to MariaDB.
 */
interface AnalyticsBuffer
{
    /**
     * Atomically claim a de-duplication key. Returns true when this is the
     * first caller within the TTL.
     */
    public function dedupe(string $key, int $ttlSeconds): bool;

    /**
     * @param  array{
     *     tenant_id: int,
     *     metric: string,
     *     entity_type: string,
     *     entity_id: string,
     *     period: string,
     *     source: string,
     *     count: int,
     *     value: float,
     *     label?: string|null,
     *     meta?: array<string, mixed>
     * }  $entry
     */
    public function record(array $entry): void;

    /**
     * Atomically read and clear all pending entries.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pull(): array;

    /**
     * Put entries back after a failed flush.
     *
     * @param  array<int, array<string, mixed>>  $entries
     */
    public function restore(array $entries): void;

    public function pending(): int;

    public function markFlushed(): void;

    public function lastFlushedAt(): ?int;

    /**
     * Optional raw-event replay stream.
     *
     * @param  array<string, mixed>  $event
     */
    public function stream(array $event): void;
}
