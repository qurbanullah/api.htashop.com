<?php

declare(strict_types=1);

namespace App\Support\Analytics;

use App\Enums\Analytics\EventName;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Transport-neutral product-analytics event.
 *
 * The same shape is produced by the browser beacon and by server-side money
 * events, so the Redis buffer and the daily rollup never need to know which
 * producer emitted a row.
 */
final class EventEnvelope
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        public readonly string $eventName,
        public readonly CarbonImmutable $occurredAt,
        public readonly string $source = 'web',
        public readonly ?string $sessionId = null,
        public readonly ?string $anonymousId = null,
        public readonly ?int $userId = null,
        public readonly ?int $tenantId = null,
        public readonly ?string $requestId = null,
        public readonly ?string $ipHash = null,
        public readonly ?string $userAgent = null,
        public readonly bool $bot = false,
        public readonly array $context = [],
    ) {}

    public static function fromArray(array $payload): self
    {
        $occurredAt = CarbonImmutable::now('UTC');

        if (isset($payload['occurred_at']) && is_string($payload['occurred_at']) && $payload['occurred_at'] !== '') {
            try {
                $occurredAt = CarbonImmutable::parse($payload['occurred_at'])->utc();
            } catch (\Throwable) {
                // Fall back to now; a malformed client timestamp must not drop
                // an otherwise valid event.
            }
        }

        return new self(
            eventName: (string) ($payload['event_name'] ?? ''),
            occurredAt: $occurredAt,
            source: (string) ($payload['source'] ?? 'web'),
            sessionId: self::nullableString($payload['session_id'] ?? null),
            anonymousId: self::nullableString($payload['anonymous_id'] ?? null),
            userId: isset($payload['user_id']) ? (int) $payload['user_id'] : null,
            tenantId: isset($payload['tenant_id']) ? (int) $payload['tenant_id'] : null,
            requestId: self::nullableString($payload['request_id'] ?? null),
            ipHash: self::nullableString($payload['ip_hash'] ?? null),
            userAgent: self::nullableString($payload['user_agent'] ?? null),
            bot: (bool) ($payload['bot'] ?? false),
            context: is_array($payload['context'] ?? null) ? $payload['context'] : [],
        );
    }

    public function toArray(): array
    {
        return [
            'event_name' => $this->eventName,
            'schema_version' => self::SCHEMA_VERSION,
            'occurred_at' => $this->occurredAt->toIso8601String(),
            'source' => $this->source,
            'session_id' => $this->sessionId,
            'anonymous_id' => $this->anonymousId,
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'request_id' => $this->requestId,
            'ip_hash' => $this->ipHash,
            'user_agent' => $this->userAgent,
            'bot' => $this->bot,
            'context' => $this->context,
        ];
    }

    public function event(): ?EventName
    {
        return EventName::tryFrom($this->eventName);
    }

    public function isBot(): bool
    {
        return $this->bot || $this->source === 'bot';
    }

    public function consentAnalytics(): bool
    {
        return (bool) data_get($this->context, 'consent.analytics', false);
    }

    public function tenantKey(): int
    {
        return $this->tenantId ?? 0;
    }

    public function productId(): ?int
    {
        $id = data_get($this->context, 'product.id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function orderId(): ?string
    {
        $id = data_get($this->context, 'order.id');

        return is_scalar($id) && (string) $id !== '' ? (string) $id : null;
    }

    public function searchQuery(): ?string
    {
        $query = data_get($this->context, 'search.query');

        return is_string($query) && trim($query) !== '' ? trim($query) : null;
    }

    public function normalisedSearchQuery(): ?string
    {
        $query = $this->searchQuery();

        if ($query === null) {
            return null;
        }

        $query = mb_strtolower($query);

        return preg_replace('/\s+/u', ' ', $query) ?? $query;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(): array
    {
        $items = $this->context['items'] ?? [];

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    public function dedupeIdentity(): ?string
    {
        return $this->sessionId ?? $this->anonymousId ?? $this->requestId;
    }

    public function searchHash(): ?string
    {
        $query = $this->normalisedSearchQuery();

        return $query !== null ? sha1($query) : null;
    }

    public function productName(): ?string
    {
        $name = data_get($this->context, 'product.name');

        return is_string($name) && trim($name) !== '' ? Str::limit(trim($name), 180, '') : null;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
