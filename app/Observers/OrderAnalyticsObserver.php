<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Jobs\Analytics\RecordEvent;
use App\Models\Order;
use App\Support\Analytics\EventEnvelope;
use App\Http\Middleware\AssignRequestId;
use Illuminate\Support\Facades\Cache;

/**
 * Emits the authoritative server-side money events.
 *
 * The browser never sends `purchase`/`refund`; those must match the committed
 * order. A Redis/cache key makes the emission idempotent across status
 * transitions and webhook retries.
 */
class OrderAnalyticsObserver
{
    private const PURCHASE_STATUSES = [
        OrderStatus::CONFIRMED,
        OrderStatus::PROCESSING,
        OrderStatus::AWAITING_SOURCING,
        OrderStatus::IN_TRANSIT,
        OrderStatus::IN_CUSTOMS,
        OrderStatus::ARRIVED_WAREHOUSE,
        OrderStatus::SHIPPED,
        OrderStatus::DELIVERED,
    ];

    public function created(Order $order): void
    {
        $this->maybeEmit($order);
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged('status')) {
            $this->maybeEmit($order);
        }
    }

    private function maybeEmit(Order $order): void
    {
        $status = (string) $order->status;
        $ttl = now()->addDays(max(7, (int) config('analytics.purchase_dedupe_days', 7)));

        if (in_array($status, self::PURCHASE_STATUSES, true)) {
            if (Cache::add('analytics:order:purchase:'.$order->id, 1, $ttl)) {
                RecordEvent::dispatch($this->payload($order, EventEnvelope::SCHEMA_VERSION, 'purchase'))->afterCommit();
            }

            return;
        }

        if ($status === OrderStatus::REFUNDED) {
            // Only refund a purchase we actually recorded; a cancelled order
            // that was never confirmed is not revenue and not a refund.
            if (
                Cache::has('analytics:order:purchase:'.$order->id)
                && Cache::add('analytics:order:refund:'.$order->id, 1, $ttl)
            ) {
                RecordEvent::dispatch($this->payload($order, EventEnvelope::SCHEMA_VERSION, 'refund'))->afterCommit();
            }
        }
    }

    private function payload(Order $order, int $schemaVersion, string $eventName): array
    {
        $order->loadMissing('items');

        $total = (float) $order->total_amount;
        $currency = (string) ($order->currency ?? 'PKR');
        $paymentMethod = data_get($order->metadata, 'payment_method')
            ?: $order->payments()->latest('id')->value('payment_method');

        $items = $order->items->take(50)->map(static fn ($item): array => [
            'product_id' => $item->product_id,
            'variant_id' => $item->variant_id,
            'sku' => $item->sku,
            'name' => $item->name,
            'quantity' => (float) $item->quantity,
            'total' => (float) $item->total,
            'currency' => $item->currency,
        ])->all();

        $analytics = is_array($order->metadata['analytics'] ?? null) ? $order->metadata['analytics'] : [];
        $campaign = is_array($analytics['campaign'] ?? null) ? $analytics['campaign'] : [];

        $context = [
            'order' => [
                'id' => $order->uuid ?? (string) $order->id,
                'value' => $total,
                'currency' => $currency,
                'payment_method' => $paymentMethod,
                'status' => $order->status,
            ],
            'cart' => [
                'items' => (float) $order->items->sum('quantity'),
                'value' => $total,
                'currency' => $currency,
            ],
            'items' => $items,
            'campaign' => $campaign,
            // Server-side events are operational records, not browser
            // behavioural tracking.
            'consent' => ['analytics' => true],
        ];

        if ($eventName === 'refund') {
            $context['refund'] = ['value' => $total, 'currency' => $currency];
        }

        return [
            'event_name' => $eventName,
            'schema_version' => $schemaVersion,
            'occurred_at' => now()->toIso8601String(),
            'source' => 'server',
            'session_id' => $analytics['session_id'] ?? null,
            'anonymous_id' => $analytics['anonymous_id'] ?? null,
            'user_id' => $order->user_id,
            'tenant_id' => $order->tenant_id,
            'request_id' => AssignRequestId::fromRequest(),
            'ip_hash' => null,
            'user_agent' => null,
            'bot' => false,
            'context' => $context,
        ];
    }
}
