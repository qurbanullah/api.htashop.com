<?php

declare(strict_types=1);

namespace App\Services\Analytics\Buffers;

use App\Contracts\Analytics\AnalyticsBuffer;
use Illuminate\Support\Facades\Redis;

/**
 * Redis-backed analytics buffer.
 *
 * Counters are stored in hashes rather than one key per counter. A day's worth
 * of product metrics is a few thousand fields, which is cheap to scan and
 * avoids `KEYS`/`SCAN` entirely.
 */
final class RedisAnalyticsBuffer implements AnalyticsBuffer
{
    private const COUNTERS = 'analytics:counters';

    private const VALUES = 'analytics:values';

    private const META = 'analytics:meta';

    private const LAST_FLUSH = 'analytics:last_flush_at';

    public function dedupe(string $key, int $ttlSeconds): bool
    {
        $script = <<<'LUA'
local ok = redis.call('SET', KEYS[1], '1', 'NX', 'EX', ARGV[1])
if ok then
    return 1
end
return 0
LUA;

        return (int) Redis::eval($script, 1, $key, max(1, $ttlSeconds)) === 1;
    }

    public function record(array $entry): void
    {
        $field = $this->field($entry);

        Redis::hincrby(self::COUNTERS, $field, (int) $entry['count']);

        $value = (float) ($entry['value'] ?? 0.0);
        if ($value !== 0.0) {
            Redis::hincrbyfloat(self::VALUES, $field, $value);
        }

        $label = $entry['label'] ?? null;
        $meta = is_array($entry['meta'] ?? null) ? $entry['meta'] : [];

        if ($label !== null || $meta !== []) {
            Redis::hset(self::META, $field, json_encode([
                'label' => $label,
                'meta' => $meta,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }

    public function pull(): array
    {
        $script = <<<'LUA'
local counters = redis.call('HGETALL', KEYS[1])
local values = redis.call('HGETALL', KEYS[2])
local meta = redis.call('HGETALL', KEYS[3])
redis.call('DEL', KEYS[1], KEYS[2], KEYS[3])
return {counters, values, meta}
LUA;

        /** @var array<int, array<int, string>> $result */
        $result = Redis::eval($script, 3, self::COUNTERS, self::VALUES, self::META);

        $counters = $this->pairs($result[0] ?? []);
        $values = $this->pairs($result[1] ?? []);
        $meta = $this->pairs($result[2] ?? []);

        $fields = array_unique(array_merge(array_keys($counters), array_keys($values), array_keys($meta)));
        $entries = [];

        foreach ($fields as $field) {
            $parts = explode('|', $field, 6);

            if (count($parts) !== 6) {
                continue;
            }

            [$tenantId, $metric, $entityType, $entityId, $period, $source] = $parts;
            $payload = json_decode($meta[$field] ?? '{}', true);
            $payload = is_array($payload) ? $payload : [];

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
                'meta' => is_array($payload['meta'] ?? null) ? $payload['meta'] : [],
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
        return (int) Redis::hlen(self::COUNTERS);
    }

    public function markFlushed(): void
    {
        Redis::set(self::LAST_FLUSH, (string) time());
    }

    public function lastFlushedAt(): ?int
    {
        $value = Redis::get(self::LAST_FLUSH);

        return $value !== null && $value !== false ? (int) $value : null;
    }

    public function stream(array $event): void
    {
        Redis::xadd(
            (string) config('analytics.stream_key', 'analytics:stream:events'),
            '*',
            ['event' => json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)],
            (int) config('analytics.stream_max_length', 100000),
            true,
        );
    }

    /**
     * Convert a Redis HGETALL flat array into a map.
     *
     * @param  array<int, string>  $flat
     * @return array<string, string>
     */
    private function pairs(array $flat): array
    {
        $pairs = [];

        for ($i = 0, $count = count($flat); $i + 1 < $count; $i += 2) {
            $pairs[(string) $flat[$i]] = (string) $flat[$i + 1];
        }

        return $pairs;
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
