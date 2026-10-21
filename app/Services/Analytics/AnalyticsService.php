<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Contracts\Analytics\AnalyticsBuffer;
use App\Enums\Analytics\EventName;
use App\Support\Analytics\EventEnvelope;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Converts a validated event into de-duplicated counter entries.
 *
 * This class never touches MariaDB. It is safe to call from a queued job or a
 * server-side observer; the scheduled `analytics:flush` is the only writer of
 * durable aggregates.
 */
final class AnalyticsService
{
    public function __construct(
        protected AnalyticsBuffer $buffer,
    ) {}

    public function record(EventEnvelope $event): bool
    {
        $name = $event->event();

        if ($name === null || ! config('analytics.enabled', true)) {
            return false;
        }

        if ($event->isBot()) {
            if (config('analytics.log_bots', false)) {
                $this->buffer->stream($event->toArray());
                $this->logEvent($event);
            }

            return false;
        }

        // Browser events require the analytics consent category. Server-side
        // money events are operational records and are not gated on it.
        if ($event->source === 'web' && ! $event->consentAnalytics()) {
            return false;
        }

        if ($this->isDuplicate($event, $name)) {
            return false;
        }

        if (config('analytics.emit_stream', false)) {
            $this->buffer->stream($event->toArray());
        }

        $this->recordEntries($event, $name);
        $this->logEvent($event);

        return true;
    }

    /**
     * Emit the searchable Loki line. High-cardinality values (ids) are placed
     * in the JSON/structured metadata, never in index labels.
     */
    private function logEvent(EventEnvelope $event): void
    {
        try {
            $device = data_get($event->context, 'device', []);
            $device = is_array($device) ? $device : [];

            $payload = array_merge([
                'service' => 'analytics',
                'event_name' => $event->eventName,
                'event_type' => $event->eventName,
                'source' => $event->source,
                'device_type' => $device['type'] ?? null,
                'browser' => $device['browser'] ?? null,
                'platform' => $device['os'] ?? null,
                'request_id' => $event->requestId,
                'user_id' => $event->userId,
                'session_id' => $event->sessionId,
                'tenant_id' => $event->tenantId,
                'product_id' => $event->productId(),
                'order_id' => $event->orderId(),
                'ip_hash' => $event->ipHash,
            ], $event->context);

            Log::channel((string) config('observability.analytics_channel', 'analytics'))
                ->info('analytics.event', $payload);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function recordEntries(EventEnvelope $event, EventName $name): void
    {
        $date = $event->occurredAt->format('Y-m-d');
        $source = $event->source;
        $tenantId = $event->tenantKey();
        $context = $event->context;

        switch ($name) {
            case EventName::ProductView:
            case EventName::AddToCart:
            case EventName::RemoveFromCart:
            case EventName::ProductImpression:
            case EventName::StockOutView:
                $this->recordProductMetric(
                    $event,
                    match ($name) {
                        EventName::ProductView => 'view',
                        EventName::AddToCart => 'add_to_cart',
                        EventName::RemoveFromCart => 'remove_from_cart',
                        EventName::ProductImpression => 'impression',
                        default => 'stock_out_view',
                    },
                    $date,
                    $source,
                    $tenantId,
                );
                break;

            case EventName::BeginCheckout:
            case EventName::AddPaymentInfo:
                $this->recordEntry(
                    tenantId: $tenantId,
                    metric: $name->value,
                    entityType: 'store',
                    entityId: (string) $tenantId,
                    period: $date,
                    source: $source,
                    count: 1,
                    value: (float) data_get($context, 'cart.value', 0),
                    label: null,
                    meta: $this->cartMeta($context),
                );
                break;

            case EventName::Purchase:
                $this->recordPurchase($event, $date, $source, $tenantId);
                break;

            case EventName::Refund:
                $this->recordRefund($event, $date, $source, $tenantId);
                break;

            case EventName::Search:
                $this->recordSearch($event, $date, $source, $tenantId);
                break;

            case EventName::SearchClick:
                $this->recordSearchClick($event, $date, $source, $tenantId);
                break;

            case EventName::PageView:
                $path = (string) data_get($context, 'page.path', '');
                $this->recordEntry(
                    tenantId: $tenantId,
                    metric: 'page_view',
                    entityType: 'page',
                    entityId: sha1($path === '' ? 'unknown' : $path),
                    period: $date,
                    source: $source,
                    count: 1,
                    value: 0.0,
                    label: $path !== '' ? Str::limit($path, 180, '') : null,
                    meta: array_filter([
                        'referrer_host' => $this->host(data_get($context, 'page.referrer_host')),
                    ]),
                );
                break;

            case EventName::ReviewSubmitted:
                $this->recordProductMetric($event, 'review', $date, $source, $tenantId, [
                    'rating' => data_get($context, 'review.rating'),
                ]);
                break;

            case EventName::CouponRejected:
                $code = (string) data_get($context, 'coupon.code', '');
                $this->recordEntry(
                    tenantId: $tenantId,
                    metric: 'coupon_rejected',
                    entityType: 'coupon',
                    entityId: sha1(mb_strtoupper($code)),
                    period: $date,
                    source: $source,
                    count: 1,
                    value: 0.0,
                    label: null,
                    meta: array_filter([
                        'reason' => Str::limit((string) data_get($context, 'coupon.reason', ''), 120, ''),
                    ]),
                );
                break;
        }
    }

    private function recordProductMetric(
        EventEnvelope $event,
        string $metric,
        string $date,
        string $source,
        int $tenantId,
        array $extraMeta = [],
    ): void {
        $productId = $event->productId();

        if ($productId === null) {
            return;
        }

        $label = $event->productName();
        $meta = array_filter(array_merge($this->productMeta($event), $extraMeta));

        $this->recordEntry(
            tenantId: $tenantId,
            metric: $metric,
            entityType: 'product',
            entityId: (string) $productId,
            period: $date,
            source: $source,
            count: 1,
            value: 0.0,
            label: $label,
            meta: $meta,
        );

        // Lifetime counters only exist for the three hot product metrics.
        if (in_array($metric, ['view', 'add_to_cart', 'purchase'], true)) {
            $this->recordEntry(
                tenantId: $tenantId,
                metric: $metric,
                entityType: 'product',
                entityId: (string) $productId,
                period: 'lifetime',
                source: 'all',
                count: 1,
                value: 0.0,
                label: $label,
                meta: $meta,
            );
        }
    }

    private function recordPurchase(EventEnvelope $event, string $date, string $source, int $tenantId): void
    {
        $orderValue = (float) data_get($event->context, 'order.value', 0);
        $currency = (string) data_get($event->context, 'order.currency', 'PKR');
        $meta = $this->orderMeta($event);

        $this->recordEntry(
            tenantId: $tenantId,
            metric: 'purchase',
            entityType: 'store',
            entityId: (string) $tenantId,
            period: $date,
            source: $source,
            count: 1,
            value: $orderValue,
            label: null,
            meta: $meta,
        );

        $this->recordEntry(
            tenantId: $tenantId,
            metric: 'revenue',
            entityType: 'store',
            entityId: (string) $tenantId,
            period: $date,
            source: $source,
            count: 0,
            value: $orderValue,
            label: null,
            meta: ['currency' => $currency] + $meta,
        );

        $items = $event->items();

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? data_get($item, 'product.id');

            if (! is_numeric($productId)) {
                continue;
            }

            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $total = (float) ($item['total'] ?? 0);
            $name = isset($item['name']) && is_string($item['name'])
                ? Str::limit($item['name'], 180, '')
                : null;

            $this->recordEntry(
                tenantId: $tenantId,
                metric: 'purchase',
                entityType: 'product',
                entityId: (string) (int) $productId,
                period: $date,
                source: $source,
                count: $quantity,
                value: $total,
                label: $name,
                meta: array_filter([
                    'sku' => $item['sku'] ?? null,
                    'variant_id' => $item['variant_id'] ?? null,
                    'currency' => $item['currency'] ?? $currency,
                ]),
            );

            $this->recordEntry(
                tenantId: $tenantId,
                metric: 'purchase',
                entityType: 'product',
                entityId: (string) (int) $productId,
                period: 'lifetime',
                source: 'all',
                count: $quantity,
                value: 0.0,
                label: $name,
                meta: [],
            );
        }
    }

    private function recordRefund(EventEnvelope $event, string $date, string $source, int $tenantId): void
    {
        $value = (float) data_get($event->context, 'refund.value', data_get($event->context, 'order.value', 0));

        $this->recordEntry(
            tenantId: $tenantId,
            metric: 'refund',
            entityType: 'store',
            entityId: (string) $tenantId,
            period: $date,
            source: $source,
            count: 1,
            value: $value,
            label: null,
            meta: $this->orderMeta($event),
        );

        foreach ($event->items() as $item) {
            $productId = $item['product_id'] ?? data_get($item, 'product.id');

            if (! is_numeric($productId)) {
                continue;
            }

            $this->recordEntry(
                tenantId: $tenantId,
                metric: 'refund',
                entityType: 'product',
                entityId: (string) (int) $productId,
                period: $date,
                source: $source,
                count: max(1, (int) ($item['quantity'] ?? 1)),
                value: (float) ($item['total'] ?? 0),
                label: isset($item['name']) && is_string($item['name']) ? Str::limit($item['name'], 180, '') : null,
                meta: [],
            );
        }
    }

    private function recordSearch(EventEnvelope $event, string $date, string $source, int $tenantId): void
    {
        $query = $event->searchQuery();
        $hash = $event->searchHash();

        if ($query === null || $hash === null) {
            return;
        }

        $searchSource = (string) data_get($event->context, 'search.source', 'results');
        $zeroResult = (bool) data_get($event->context, 'search.zero_result', false);

        $meta = [
            'query' => $query,
            'search_source' => $searchSource,
            'results_count' => (int) data_get($event->context, 'search.results_count', 0),
            'zero_result' => $zeroResult,
        ];

        $this->recordEntry(
            tenantId: $tenantId,
            metric: 'search',
            entityType: 'search',
            entityId: $hash,
            period: $date,
            source: $source,
            count: 1,
            value: 0.0,
            label: Str::limit($query, 180, ''),
            meta: $meta,
        );

        if ($zeroResult) {
            $this->recordEntry(
                tenantId: $tenantId,
                metric: 'search_zero_result',
                entityType: 'search',
                entityId: $hash,
                period: $date,
                source: $source,
                count: 1,
                value: 0.0,
                label: Str::limit($query, 180, ''),
                meta: $meta,
            );
        }
    }

    private function recordSearchClick(EventEnvelope $event, string $date, string $source, int $tenantId): void
    {
        $query = $event->searchQuery();
        $hash = $event->searchHash();

        if ($query === null || $hash === null) {
            return;
        }

        $this->recordEntry(
            tenantId: $tenantId,
            metric: 'search_click',
            entityType: 'search',
            entityId: $hash,
            period: $date,
            source: $source,
            count: 1,
            value: 0.0,
            label: Str::limit($query, 180, ''),
            meta: array_filter([
                'query' => $query,
                'product_id' => data_get($event->context, 'product.id'),
            ]),
        );
    }

    private function recordEntry(
        int $tenantId,
        string $metric,
        string $entityType,
        string $entityId,
        string $period,
        string $source,
        int $count,
        float $value,
        ?string $label,
        array $meta,
    ): void {
        $this->buffer->record([
            'tenant_id' => $tenantId,
            'metric' => $metric,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'period' => $period,
            'source' => $source,
            'count' => $count,
            'value' => $value,
            'label' => $label,
            'meta' => $meta,
        ]);
    }

    private function isDuplicate(EventEnvelope $event, EventName $name): bool
    {
        $identity = $event->dedupeIdentity();
        $dedupeMinutes = max(1, (int) config('analytics.dedupe_minutes', 30));

        $key = null;
        $ttl = $dedupeMinutes * 60;

        switch ($name) {
            case EventName::Purchase:
            case EventName::Refund:
                $orderId = $event->orderId();

                if ($orderId === null) {
                    return false;
                }

                $key = 'analytics:dedupe:'.$name->value.':order:'.$orderId;
                $ttl = max(1, (int) config('analytics.purchase_dedupe_days', 7)) * 86400;
                break;

            case EventName::Search:
                if ($identity === null || $event->searchHash() === null) {
                    return false;
                }

                $searchSource = (string) data_get($event->context, 'search.source', 'results');
                $key = 'analytics:dedupe:search:'.$searchSource.':'.$identity.':'.$event->searchHash();
                $ttl = $searchSource === 'suggest'
                    ? max(1, (int) config('analytics.search_suggest_dedupe_minutes', 10)) * 60
                    : max(1, (int) config('analytics.search_results_dedupe_seconds', 60));
                break;

            case EventName::PageView:
                if ($identity === null) {
                    return false;
                }

                $path = (string) data_get($event->context, 'page.path', 'unknown');
                $key = 'analytics:dedupe:page_view:'.$identity.':'.sha1($path);
                $ttl = 60;
                break;

            case EventName::BeginCheckout:
            case EventName::AddPaymentInfo:
                if ($identity === null) {
                    return false;
                }

                $key = 'analytics:dedupe:'.$name->value.':'.$identity;
                $ttl = 300;
                break;

            case EventName::ProductView:
            case EventName::AddToCart:
            case EventName::RemoveFromCart:
            case EventName::ProductImpression:
            case EventName::StockOutView:
                $productId = $event->productId();

                if ($identity === null || $productId === null) {
                    return false;
                }

                $key = 'analytics:dedupe:'.$name->value.':'.$identity.':'.$productId;
                break;

            case EventName::SearchClick:
                if ($identity === null || $event->searchHash() === null) {
                    return false;
                }

                $productId = (string) (data_get($event->context, 'product.id') ?? 'none');
                $key = 'analytics:dedupe:search_click:'.$identity.':'.$event->searchHash().':'.$productId;
                $ttl = 300;
                break;

            default:
                return false;
        }

        if ($key === null) {
            return false;
        }

        return ! $this->buffer->dedupe($key, $ttl);
    }

    /**
     * @return array<string, mixed>
     */
    private function productMeta(EventEnvelope $event): array
    {
        $product = data_get($event->context, 'product', []);

        return array_filter([
            'name' => $event->productName(),
            'category_id' => is_array($product) ? ($product['category_id'] ?? null) : null,
            'price' => is_array($product) ? ($product['price'] ?? null) : null,
            'currency' => is_array($product) ? ($product['currency'] ?? null) : null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function orderMeta(EventEnvelope $event): array
    {
        return array_filter([
            'order_id' => $event->orderId(),
            'payment_method' => data_get($event->context, 'order.payment_method'),
            'status' => data_get($event->context, 'order.status'),
            'currency' => data_get($event->context, 'order.currency'),
            'utm_source' => data_get($event->context, 'campaign.utm_source'),
            'utm_campaign' => data_get($event->context, 'campaign.utm_campaign'),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function cartMeta(array $context): array
    {
        return array_filter([
            'items' => data_get($context, 'cart.items'),
            'currency' => data_get($context, 'cart.currency'),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    private function host(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $host = parse_url(str_contains($value, '://') ? $value : 'https://'.$value, PHP_URL_HOST);

        return is_string($host) ? Str::limit(strtolower($host), 120, '') : null;
    }
}
