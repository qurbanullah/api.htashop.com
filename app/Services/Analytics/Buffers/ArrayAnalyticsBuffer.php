<?php

declare(strict_types=1);

namespace App\Services\Analytics\Buffers;

use App\Contracts\Analytics\AnalyticsBuffer;

/**
 * In-memory buffer used by the test suite and local `ANALYTICS_BUFFER=array`
 * runs. It implements the same entry shape as the Redis driver.
 */
final class ArrayAnalyticsBuffer implements AnalyticsBuffer
{
    /** @var array<string, int> */
    private array $counters = [];

    /** @var array<string, float> */
    private array $values = [];

    /** @var array<string, array{label: ?string, meta: array<string, mixed>}> */
    private array $meta = [];

    /** @var array<string, int> */
    private array $dedupeUntil = [];

    /** @var array<int, array<string, mixed>> */
    private array $streamEvents = [];

    private ?int $lastFlushedAt = null;

    public function dedupe(string $key, int $ttlSeconds): bool
    {
        $now = time();

        if (($this->dedupeUntil[$key] ?? 0) > $now) {
            return false;
        }

        $this->dedupeUntil[$key] = $now + max(1, $ttlSeconds);

        return true;
    }

    public function record(array $entry): void
    {
        $field = $this->field($entry);

        $this->counters[$field] = ($this->counters[$field] ?? 0) + (int) $entry['count'];

        $value = (float) ($entry['value'] ?? 0.0);
        if ($value !== 0.0) {
            $this->values[$field] = ($this->values[$field] ?? 0.0) + $value;
        }

        $label = $entry['label'] ?? null;
        $meta = is_array($entry['meta'] ?? null) ? $entry['meta'] : [];

        if ($label !== null || $meta !== []) {
            $this->meta[$field] = ['label' => $label, 'meta' => $meta];
        }
    }

    public function pull(): array
    {
        $counters = $this->counters;
        $values = $this->values;
        $meta = $this->meta;

        $this->counters = [];
        $this->values = [];
        $this->meta = [];

        $fields = array_unique(array_merge(array_keys($counters), array_keys($values), array_keys($meta)));
        $entries = [];

        foreach ($fields as $field) {
            $parts = explode('|', $field, 6);

            if (count($parts) !== 6) {
                continue;
            }

            [$tenantId, $metric, $entityType, $entityId, $period, $source] = $parts;
            $payload = $meta[$field] ?? [];

            $entries[] = [
                'tenant_id' => (int) $tenantId,
                'metric' => $metric,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'period' => $period,
                'source' => $source,
                'count' => (int) ($counters[$field] ?? 0),
                'value' => (float) ($values[$field] ?? 0.0),
                'label' => $payload['label'] ?? null,
                'meta' => $payload['meta'] ?? [],
            ];
        }

        return $entries;
    }

    public function restore(array $entries): void
    {
        foreach ($entries as $entry) {
            $this->record($entry);
        }
    }

    public function pending(): int
    {
        return count($this->counters);
    }

    public function markFlushed(): void
    {
        $this->lastFlushedAt = time();
    }

    public function lastFlushedAt(): ?int
    {
        return $this->lastFlushedAt;
    }

    public function stream(array $event): void
    {
        $this->streamEvents[] = $event;

        if (count($this->streamEvents) > 1000) {
            array_shift($this->streamEvents);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function streamedEvents(): array
    {
        return $this->streamEvents;
    }

    private function field(array $entry): string
    {
        return implode('|', [
            (int) ($entry['tenant_id'] ?? 0),
            (string) $entry['metric'],
            (string) $entry['entity_type'],
            (string) $entry['entity_id'],
            (string) $entry['period'],
            (string) $entry['source'],
        ]);
    }
}
