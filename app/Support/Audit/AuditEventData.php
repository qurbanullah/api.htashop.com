<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Http\Middleware\AssignRequestId;
use App\Models\User;
use App\Support\Observability\IpHasher;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Normalised audit event, independent of how the caller discovered it
 * (observer, listener or explicit service call).
 *
 * Everything that lands in the `audits` table and in the `audit` log channel is
 * built here, so redaction, IP hashing and request correlation cannot drift
 * between the two paths.
 */
final class AuditEventData
{
    /**
     * Keys whose values must never be persisted. Matching is case-insensitive
     * and substring-based (`password_confirmation` matches `password`).
     */
    private const SENSITIVE_KEYS = [
        'password',
        'passwd',
        'remember_token',
        'access_token',
        'refresh_token',
        'token',
        'authorization',
        'cookie',
        'secret',
        'api_key',
        'private_key',
        'card',
        'cvv',
        'cvc',
        'iban',
        'ip_address',
        'raw_ip',
    ];

    private const MAX_DEPTH = 4;

    private const RESERVED_META_KEYS = [
        'event',
        'actor_type',
        'actor_id',
        'acted_as_type',
        'acted_as_id',
        'old_values',
        'new_values',
        'changed_columns',
        'reason',
        'description',
        'tenant_id',
        'request_id',
        'ip_hash',
        'user_agent',
        'occurred_at',
    ];

    public function __construct(
        public readonly string $event,
        public readonly string $actorType,
        public readonly int|string|null $actorId,
        public readonly ?string $actedAsType,
        public readonly int|string|null $actedAsId,
        public readonly ?string $auditableType,
        public readonly int|string|null $auditableId,
        public readonly ?string $auditableTypeName,
        public readonly ?array $oldValues,
        public readonly ?array $newValues,
        public readonly ?array $changedColumns,
        public readonly ?string $reason,
        public readonly ?string $description,
        public readonly ?int $tenantId,
        public readonly ?string $requestId,
        public readonly ?string $ipHash,
        public readonly ?string $userAgent,
        public readonly Carbon $occurredAt,
        public readonly array $meta,
    ) {}

    /**
     * Build from the legacy `AuditService::record()` argument shape used across
     * the application (and from the observer/listener call sites).
     */
    public static function fromLegacy(?User $actor, string $event, mixed $auditable = null, array $meta = []): self
    {
        $auditableType = null;
        $auditableId = null;
        $tenantId = isset($meta['tenant_id']) ? (int) $meta['tenant_id'] : null;

        if ($auditable instanceof Model) {
            $auditableType = $auditable::class;
            $auditableId = $auditable->getKey();

            if ($tenantId === null && isset($auditable->tenant_id) && is_numeric($auditable->tenant_id)) {
                $tenantId = (int) $auditable->tenant_id;
            }
        } elseif (is_array($auditable) && isset($auditable['type'])) {
            $auditableType = (string) $auditable['type'];
            $auditableId = $auditable['id'] ?? null;
        } elseif (is_string($auditable) && $auditable !== '') {
            $auditableType = $auditable;
        }

        $actorType = (string) ($meta['actor_type'] ?? ($actor ? User::class : 'system'));
        $actorId = $meta['actor_id'] ?? $actor?->getKey();

        [$requestId, $ipHash, $userAgent] = self::requestContext($meta);

        $oldValues = self::sanitiseArray($meta['old_values'] ?? null);
        $newValues = self::sanitiseArray($meta['new_values'] ?? null);
        $changedColumns = $meta['changed_columns'] ?? self::changedColumns($oldValues, $newValues);

        $occurredAt = self::parseDate($meta['occurred_at'] ?? null) ?? now();

        $eventValue = $event !== '' ? $event : (string) ($meta['event'] ?? 'unknown');

        return new self(
            event: $eventValue,
            actorType: $actorType,
            actorId: $actorId,
            actedAsType: $meta['acted_as_type'] ?? null,
            actedAsId: $meta['acted_as_id'] ?? null,
            auditableType: $auditableType,
            auditableId: $auditableId,
            auditableTypeName: $meta['auditable_type_name'] ?? ($auditableType ? class_basename($auditableType) : null),
            oldValues: $oldValues,
            newValues: $newValues,
            changedColumns: $changedColumns,
            reason: isset($meta['reason']) ? (string) $meta['reason'] : null,
            description: isset($meta['description']) ? (string) $meta['description'] : null,
            tenantId: $tenantId,
            requestId: $requestId,
            ipHash: $ipHash,
            userAgent: $userAgent,
            occurredAt: $occurredAt,
            meta: self::cleanMeta($meta),
        );
    }

    /**
     * Column map for the `audits` table.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'actor_type' => $this->actorType,
            'actor_id' => $this->actorId,
            'acted_as_type' => $this->actedAsType,
            'acted_as_id' => $this->actedAsId,
            'user_id' => $this->actorType === User::class ? $this->actorId : null,
            'event' => $this->event,
            'auditable_type' => $this->auditableType,
            'auditable_id' => $this->auditableId,
            'auditable_type_name' => $this->auditableTypeName,
            'old_values' => $this->oldValues ?: null,
            'new_values' => $this->newValues ?: null,
            'changed_columns' => $this->changedColumns ?: null,
            'reason' => $this->reason,
            'description' => $this->description,
            'request_id' => $this->requestId,
            'ip_hash' => $this->ipHash,
            'user_agent' => $this->userAgent,
            'occurred_at' => $this->occurredAt,
            'meta' => $this->meta ?: null,
        ];
    }

    /**
     * Context fields copied into the Loki line (high cardinality, never labels).
     *
     * @return array<string, mixed>
     */
    public function toLogContext(): array
    {
        return [
            'service' => 'audit',
            'uuid' => null, // filled by the logger after the row is created
            'event' => $this->event,
            'actor_type' => $this->actorType,
            'actor_id' => $this->actorId,
            'acted_as_type' => $this->actedAsType,
            'acted_as_id' => $this->actedAsId,
            'auditable_type' => $this->auditableType,
            'auditable_id' => $this->auditableId,
            'changed_columns' => $this->changedColumns,
            'reason' => $this->reason,
            'request_id' => $this->requestId,
            'ip_hash' => $this->ipHash,
            'user_agent' => $this->userAgent,
            'occurred_at' => $this->occurredAt->toIso8601String(),
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private static function requestContext(array $meta): array
    {
        $requestId = isset($meta['request_id']) ? (string) $meta['request_id'] : AssignRequestId::fromRequest();
        $ipHash = isset($meta['ip_hash']) ? (string) $meta['ip_hash'] : null;
        $userAgent = isset($meta['user_agent']) ? Str::limit((string) $meta['user_agent'], 512, '') : null;

        if ($ipHash !== null && $userAgent !== null) {
            return [$requestId, $ipHash, $userAgent];
        }

        // A CLI/queue context has no HTTP request; never record the worker's
        // loopback address as if it were the actor's.
        if (app()->runningInConsole() && $requestId === null) {
            return [$requestId, $ipHash, $userAgent];
        }

        $request = request();

        if (! $request instanceof Request) {
            return [$requestId, $ipHash, $userAgent];
        }

        $ipHash ??= IpHasher::hash($request->ip());
        $userAgent ??= $request->userAgent() ? Str::limit($request->userAgent(), 512, '') : null;

        return [$requestId, $ipHash, $userAgent];
    }

    private static function parseDate(mixed $value): ?Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private static function changedColumns(?array $old, ?array $new): ?array
    {
        $old ??= [];
        $new ??= [];

        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        $changed = [];

        foreach ($keys as $key) {
            if (($old[$key] ?? null) !== ($new[$key] ?? null)) {
                $changed[] = $key;
            }
        }

        return $changed === [] ? null : array_values($changed);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function sanitiseArray(mixed $value): ?array
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        $sanitised = self::sanitise($value, 0);

        return is_array($sanitised) && $sanitised !== [] ? $sanitised : null;
    }

    private static function sanitise(mixed $value, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            return '[truncated]';
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_array($value)) {
            $clean = [];

            foreach ($value as $key => $item) {
                if (is_string($key) && self::isSensitiveKey($key)) {
                    $clean[$key] = '[redacted]';

                    continue;
                }

                $clean[$key] = self::sanitise($item, $depth + 1);
            }

            return $clean;
        }

        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string) $value : '[object]';
        }

        return $value;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keep useful extra context (e.g. old/new role names) but drop the keys that
     * already have dedicated columns.
     *
     * @return array<string, mixed>
     */
    private static function cleanMeta(array $meta): array
    {
        foreach (self::RESERVED_META_KEYS as $key) {
            unset($meta[$key]);
        }

        $clean = self::sanitise($meta, 0);

        return is_array($clean) ? $clean : [];
    }
}
