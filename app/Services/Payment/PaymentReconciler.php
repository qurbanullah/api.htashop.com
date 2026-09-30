<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TransactionType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Applies a payment outcome to the payment, the ledger and the order.
 *
 * Every gateway callback, status poll and refund funnels through here, so the
 * state machine lives in exactly one place.
 *
 * Webhooks are *at-least-once*: a gateway that does not receive a 2xx will
 * resend, and a payment can legitimately produce `payment.succeeded` twice.
 * `sync()` is therefore idempotent and locks the row, which also makes it safe
 * with several API replicas handling callbacks concurrently.
 */
class PaymentReconciler
{
    /**
     * Statuses a payment is allowed to move *into* from its current state.
     * Anything else is ignored rather than applied: a late `failed` webhook
     * must never un-pay a payment that already succeeded.
     */
    private const TRANSITIONS = [
        PaymentStatus::PENDING => [
            PaymentStatus::AUTHORIZED,
            PaymentStatus::PAID,
            PaymentStatus::FAILED,
            PaymentStatus::CANCELLED,
        ],
        PaymentStatus::AUTHORIZED => [
            PaymentStatus::PAID,
            PaymentStatus::FAILED,
            PaymentStatus::CANCELLED,
        ],
        PaymentStatus::PAID => [
            PaymentStatus::REFUNDED,
        ],
        PaymentStatus::FAILED => [
            PaymentStatus::PAID,
        ],
        PaymentStatus::CANCELLED => [
            PaymentStatus::PAID,
        ],
        PaymentStatus::REFUNDED => [],
    ];

    public function sync(
        Payment $payment,
        string $status,
        array $payload = [],
        ?string $gatewayTransactionId = null,
        ?float $amount = null,
    ): Payment {
        if (! in_array($status, PaymentStatus::ALL, true)) {
            throw new \InvalidArgumentException("Unknown payment status: {$status}");
        }

        return DB::transaction(function () use ($payment, $status, $payload, $gatewayTransactionId, $amount) {
            // Re-read under a lock: the webhook may have been delivered to a
            // different replica at the same time as this call.
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $current = $locked->status;

            if ($current === $status) {
                // Duplicate delivery — record the payload for audit and stop.
                $locked->update(['gateway_payload' => $this->mergePayload($locked, $payload)]);

                return $locked;
            }

            if (! in_array($status, self::TRANSITIONS[$current] ?? [], true)) {
                // Out-of-order delivery. Keep what we have and record the event.
                $locked->update(['gateway_payload' => $this->mergePayload($locked, $payload, 'ignored')]);

                return $locked;
            }

            $settled = $amount ?? (float) $locked->amount;

            $locked->update([
                'status' => $status,
                'transaction_reference' => $gatewayTransactionId ?: $locked->transaction_reference,
                'gateway_payload' => $this->mergePayload($locked, $payload),
            ]);

            $this->writeLedger($locked, $status, $settled, $gatewayTransactionId);
            $this->advanceOrder($locked->order, $status);

            return $locked->fresh();
        });
    }

    /**
     * The ledger is append-only: one row per money movement, never updated.
     */
    private function writeLedger(Payment $payment, string $status, float $amount, ?string $gatewayTransactionId): void
    {
        [$type, $ledgerStatus] = match ($status) {
            PaymentStatus::PAID => [TransactionType::CHARGE, 'succeeded'],
            PaymentStatus::REFUNDED => [TransactionType::REFUND, 'succeeded'],
            PaymentStatus::FAILED, PaymentStatus::CANCELLED => [TransactionType::CHARGE, 'failed'],
            // authorized / pending move no money.
            default => [null, null],
        };

        if ($type === null) {
            return;
        }

        // A retried webhook for the same outcome must not double-post.
        $alreadyRecorded = $payment->transactions()
            ->where('type', $type)
            ->where('status', $ledgerStatus)
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        Transaction::create([
            'payment_id' => $payment->id,
            'type' => $type,
            'amount' => $amount,
            'currency' => $payment->currency,
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => $ledgerStatus,
        ]);
    }

    /**
     * Money received confirms the order; money returned refunds it. A failure
     * leaves the order pending so the customer can pay another way.
     */
    private function advanceOrder(?Order $order, string $status): void
    {
        if (! $order) {
            return;
        }

        if ($status === PaymentStatus::PAID && $order->status === OrderStatus::PENDING) {
            $order->update([
                'status' => OrderStatus::CONFIRMED,
                'confirmed_at' => $order->confirmed_at ?? now(),
            ]);

            return;
        }

        if ($status === PaymentStatus::REFUNDED) {
            $order->update(['status' => OrderStatus::REFUNDED]);
        }
    }

    /**
     * Keep the gateway's own payload for support and audits. The `outcome`
     * tag records events we chose not to apply.
     */
    private function mergePayload(Payment $payment, array $payload, ?string $outcome = null): array
    {
        $existing = (array) ($payment->gateway_payload ?? []);

        if ($payload === []) {
            return $existing;
        }

        $events = (array) ($existing['webhook_events'] ?? []);
        $events[] = array_filter([
            'at' => now()->toIso8601String(),
            'outcome' => $outcome,
            'payload' => $payload,
        ]);

        // Bound the history so a chatty gateway cannot grow the row forever.
        $existing['webhook_events'] = array_slice($events, -20);

        return $existing;
    }
}
