<?php

namespace App\Gateways;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TransactionType;
use App\Gateways\Safepay\SafepayClient;
use App\Gateways\Safepay\SafepaySignature;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\PaymentReconciler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Safepay — hosted checkout.
 *
 * The customer never enters card details into our app: we create a payment
 * session server-side, hand back a checkout URL, and Safepay redirects back to
 * us once the outcome is known. The webhook is the authoritative signal; the
 * redirect is only a UX affordance.
 *
 * @see docs/PAYMENTS.md
 */
class SafepayGateway implements PaymentGateway
{
    public function __construct(
        protected SafepayClient $client,
        protected PaymentReconciler $reconciler,
        protected array $config = [],
    ) {}

    public function method(): string
    {
        return PaymentMethod::SAFEPAY;
    }

    public function label(): string
    {
        return PaymentMethod::label(PaymentMethod::SAFEPAY);
    }

    /**
     * Enabled *and* configured. The master switch wins, and both keys must be
     * present — the public key identifies the merchant, the secret key
     * authenticates us.
     */
    public function isEnabled(): bool
    {
        return (bool) config('payment.enabled', true)
            && (bool) ($this->config['enabled'] ?? false)
            && $this->client->isConfigured();
    }

    public function supportsCurrency(?string $currency): bool
    {
        $currencies = (array) ($this->config['currencies'] ?? []);

        if ($currencies === [] || blank($currency)) {
            return true;
        }

        return in_array(strtoupper((string) $currency), array_map('strtoupper', $currencies), true);
    }

    /**
     * Create (or reuse) the payment row, open a Safepay session and return the
     * hosted checkout URL.
     */
    public function initialize(Order $order): PaymentInitResult
    {
        $payment = $this->pendingPayment($order);

        $session = $this->client->createSession($order);
        $tracker = $this->extractTracker($session);

        if ($tracker === null) {
            throw new \RuntimeException('Safepay did not return a tracker for order '.$order->uuid.'.');
        }

        // Metadata lets a webhook be matched back to the order deterministically
        // instead of by amount or heuristics. It is an optimisation, not a step
        // in taking money, so it is best-effort — see `attachMetadata()`.
        $this->attachMetadata($tracker, $order);

        $token = $this->extractTracker($this->client->createTimeBasedToken());

        if ($token === null) {
            throw new \RuntimeException('Safepay did not return a time-based token.');
        }

        $redirectUrl = (string) config('payment.redirects.success');
        $cancelUrl = (string) config('payment.redirects.cancel');

        $checkoutUrl = $this->client->checkoutUrl($tracker, $token, $redirectUrl, $cancelUrl, $order->uuid);

        $payment->update([
            'transaction_reference' => $tracker,
            'gateway_payload' => array_merge((array) $payment->gateway_payload, [
                'environment' => $this->client->environment(),
                'tracker' => $tracker,
                'checkout_url' => $checkoutUrl,
                'amount_minor' => $this->client->toMinorUnits($order->total_amount),
                'session' => $session,
            ]),
        ]);

        return new PaymentInitResult(
            requiresRedirect: true,
            redirectUrl: $checkoutUrl,
            payload: ['tracker' => $tracker],
        );
    }

    /**
     * Attach our identifiers to the session, tolerating rejection.
     *
     * Safepay accepts only a small, undocumented set of meta keys — `order_id`
     * and `source` among them; `payment_uuid`, `order_number` and `reference`
     * are refused with a 400. Because that endpoint can reject a key we believe
     * is valid, a failure here must never abort the session: the customer is
     * still one redirect away from paying, and a webhook we cannot match by
     * metadata is still matched by its tracker (see `resolvePayment()`).
     */
    private function attachMetadata(string $tracker, Order $order): void
    {
        try {
            $this->client->attachMetadata($tracker, [
                'source' => (string) config('app.name'),
                'order_id' => $order->uuid,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Safepay rejected the session metadata; webhook matching will fall back to the tracker.', [
                'order' => $order->uuid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Ask Safepay what the payment actually did, and apply it.
     */
    public function verify(Payment $payment): string
    {
        $tracker = $payment->transaction_reference
            ?: data_get($payment->gateway_payload, 'tracker');

        if (blank($tracker)) {
            return $payment->status;
        }

        $remote = $this->client->retrievePayment((string) $tracker);
        $status = $this->mapState($this->extractState($remote));

        return $this->reconciler->sync(
            $payment,
            $status,
            ['source' => 'verify', 'response' => $remote],
            $this->extractGatewayTransactionId($remote),
        )->status;
    }

    /**
     * Handle the `payment.*` webhook. Returns false when the request was not a
     * payment event we act on (or could not be attributed to a payment).
     */
    public function handleCallback(Request $request): bool
    {
        $payload = $request->getContent();
        $signature = $request->header(SafepaySignature::HEADER);
        $secret = $this->config['webhook_secret'] ?? null;

        if (! $this->client->hasWebhookSecret()) {
            // Refusing is the safe failure: an unsigned request is
            // indistinguishable from an attacker marking orders paid.
            Log::warning('Safepay webhook rejected: no webhook secret configured.');

            throw new WebhookRejectedException('Safepay webhook secret is not configured.');
        }

        if (! $this->signatureIsValid($payload, $signature, (string) $secret)) {
            Log::warning('Safepay webhook rejected: signature mismatch.');

            throw new WebhookRejectedException('Safepay webhook signature did not verify.');
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return false;
        }

        $type = (string) ($event['type'] ?? data_get($event, 'data.type', ''));
        $data = (array) ($event['data'] ?? []);

        $payment = $this->resolvePayment($data);

        if (! $payment) {
            // A signature-valid event we cannot place. Worth an operator's
            // attention, but not worth failing the delivery over.
            Log::warning('Safepay webhook could not be matched to a payment.', ['type' => $type]);

            return false;
        }

        $status = $this->mapEventType($type) ?? $this->mapState($this->extractState($data));

        $this->reconciler->sync(
            $payment,
            $status,
            ['type' => $type, 'data' => $data],
            $this->extractGatewayTransactionId($data),
        );

        return true;
    }

    /**
     * Refund at Safepay, then record it locally. Safepay needs the transaction
     * id it gave us when the charge settled; the tracker is the fallback for
     * accounts that answer on the order endpoint instead.
     */
    public function refund(Payment $payment, ?float $amount = null, ?string $reason = null): void
    {
        $transactionId = $payment->transactions()
            ->where('type', TransactionType::CHARGE)
            ->where('status', 'succeeded')
            ->value('gateway_transaction_id')
            ?: $payment->transaction_reference;

        $response = $this->client->refund(
            $transactionId ?: null,
            $amount === null ? null : $this->client->toMinorUnits($amount),
            $reason,
        );

        $this->reconciler->sync(
            $payment,
            PaymentStatus::REFUNDED,
            ['source' => 'refund', 'response' => $response],
            $this->extractGatewayTransactionId($response),
            $amount,
        );
    }

    /**
     * Safepay signs the exact bytes it sent. We accept the normalised JSON
     * encoding as a fallback because Safepay's own PHP sample re-encodes the
     * body before verifying; a proxy that rewrites whitespace would otherwise
     * break every callback.
     */
    private function signatureIsValid(string $payload, ?string $signature, string $secret): bool
    {
        if (SafepaySignature::verify($payload, $signature, $secret)) {
            return true;
        }

        $normalised = json_encode(json_decode($payload, true), JSON_UNESCAPED_SLASHES);

        return is_string($normalised) && SafepaySignature::verify($normalised, $signature, $secret);
    }

    /**
     * The pending payment for this order, or a new one. Reusing the row keeps a
     * customer who retries checkout from littering the ledger with attempts.
     */
    private function pendingPayment(Order $order): Payment
    {
        $existing = $order->payments()
            ->where('payment_method', PaymentMethod::SAFEPAY)
            ->where('status', PaymentStatus::PENDING)
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return Payment::create([
            'order_id' => $order->id,
            'payment_method' => PaymentMethod::SAFEPAY,
            'status' => PaymentStatus::PENDING,
            'amount' => $order->total_amount,
            'currency' => $order->currency,
        ]);
    }

    /**
     * Safepay nests the token differently across endpoints (`tracker.token`,
     * `token`, or a bare string), so tolerate all three.
     */
    private function extractTracker(array $response): ?string
    {
        $candidates = [
            data_get($response, 'tracker.token'),
            data_get($response, 'token'),
            data_get($response, 'tracker'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function extractState(array $data): ?string
    {
        foreach (['state', 'status', 'payment_state', 'transaction_state'] as $key) {
            $value = data_get($data, $key);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function extractGatewayTransactionId(array $data): ?string
    {
        foreach (['id', 'transaction_id', 'token', 'tracker.token', 'reference'] as $key) {
            $value = data_get($data, $key);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Match the event to a payment using our own identifiers first — they are
     * the only values we can be certain about.
     */
    private function resolvePayment(array $data): ?Payment
    {
        $paymentUuid = $this->metaScalar(data_get($data, 'metadata.payment_uuid'))
            ?? $this->metaScalar(data_get($data, 'metadata.data.payment_uuid'));

        if ($paymentUuid) {
            $payment = Payment::query()->where('uuid', $paymentUuid)->first();
            if ($payment) {
                return $payment;
            }
        }

        $orderUuid = $this->metaScalar(data_get($data, 'metadata.order_id'))
            ?? $this->metaScalar(data_get($data, 'metadata.data.order_id'))
            ?? $this->metaScalar(data_get($data, 'order_id'));

        if ($orderUuid) {
            $payment = Payment::query()
                ->whereHas('order', fn ($query) => $query->where('uuid', $orderUuid))
                ->latest('id')
                ->first();

            if ($payment) {
                return $payment;
            }
        }

        foreach (['tracker.token', 'tracker', 'reference', 'token'] as $key) {
            $tracker = data_get($data, $key);

            if (is_string($tracker) && $tracker !== '') {
                $payment = Payment::query()
                    ->where(fn ($query) => $query
                        ->where('transaction_reference', $tracker)
                        ->orWhere('gateway_payload->tracker', $tracker))
                    ->latest('id')
                    ->first();

                if ($payment) {
                    return $payment;
                }
            }
        }

        return null;
    }

    /**
     * A metadata value, as a scalar we can match on.
     *
     * Safepay is inconsistent about this shape: the checkout and webhook
     * payloads send a plain string, while the reporter (`retrievePayment`)
     * returns `{key, value, …}` objects. Picking `value` keeps both readable
     * and avoids handing an array to a `where()` clause.
     */
    private function metaScalar(mixed $value): ?string
    {
        if (is_string($value) || is_int($value)) {
            return (string) $value;
        }

        if (is_array($value) && is_scalar($value['value'] ?? null)) {
            return (string) $value['value'];
        }

        return null;
    }

    /**
     * The event type is the contract; the state string is a fallback.
     */
    private function mapEventType(string $type): ?string
    {
        return match ($type) {
            'payment.succeeded', 'subscription.payment.succeeded' => PaymentStatus::PAID,
            'payment.failed' => PaymentStatus::FAILED,
            'payment.refunded' => PaymentStatus::REFUNDED,
            'authorization.reversed', 'void.succeeded' => PaymentStatus::CANCELLED,
            default => null,
        };
    }

    /**
     * Safepay's state vocabulary is larger than ours and has changed over
     * versions, so map generously and default to leaving the payment pending.
     *
     * The lifecycle states are `TRACKER_*` (read from Safepay's own checkout
     * bundle): a tracker goes `TRACKER_STARTED` → … → **`TRACKER_ENDED`** when
     * the money is captured. Missing `TRACKER_ENDED` here is what left paid
     * customers on "awaiting confirmation" — the webhook path uses the event
     * type, but `verify()` (the status poll behind the return page) only has
     * the state string.
     */
    private function mapState(?string $state): string
    {
        $normalised = strtoupper(trim((string) $state));

        return match (true) {
            // Money captured.
            in_array($normalised, ['TRACKER_ENDED', 'PAID', 'SETTLED', 'CAPTURED', 'SUCCESS', 'SUCCEEDED', 'COMPLETED', 'CHARGED'], true) => PaymentStatus::PAID,
            // Money returned. Partial refunds are recorded as a refund: we have
            // no partial state, and the ledger carries the amount.
            in_array($normalised, ['TRACKER_REFUNDED', 'TRACKER_PARTIAL_REFUND', 'REFUNDED', 'PARTIALLY_REFUNDED', 'PARTIAL_REFUND'], true) => PaymentStatus::REFUNDED,
            in_array($normalised, ['TRACKER_AUTHORIZED', 'AUTHORIZED', 'AUTHORISED', 'AUTH'], true) => PaymentStatus::AUTHORIZED,
            // Voided/reversed release an authorization without settling, which
            // is a cancellation — matching `mapEventType()`.
            in_array($normalised, ['TRACKER_VOIDED', 'TRACKER_REVERSED', 'TRACKER_CANCELLED', 'VOIDED', 'VOID', 'REVERSED', 'CANCELLED', 'CANCELED'], true) => PaymentStatus::CANCELLED,
            in_array($normalised, ['TRACKER_EXPIRED', 'FAILED', 'DECLINED', 'ERROR', 'EXPIRED', 'REJECTED', 'ABANDONED'], true) => PaymentStatus::FAILED,
            // Started / enrolled / disputed / not-found / anything new stays
            // pending. Guessing "failed" would tell a customer their payment
            // failed when we simply do not know, and the reconciler refuses to
            // downgrade a paid payment anyway.
            default => PaymentStatus::PENDING,
        };
    }
}
