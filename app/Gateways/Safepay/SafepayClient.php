<?php

namespace App\Gateways\Safepay;

use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around Safepay's REST API.
 *
 * Deliberately hand-rolled rather than pulling in the vendor SDK: we need three
 * endpoints, and having them typed and testable here keeps the gateway layer
 * free of a transitive dependency.
 *
 * Auth model (Safepay "secret" auth type):
 *   - the *secret* key authenticates this server, in `X-SFPY-MERCHANT-SECRET`
 *   - the *public* key identifies the merchant, as `merchant_api_key` in the body
 *
 * Amounts are sent in the currency's minor unit (paisas for PKR).
 *
 * Flow, in the order the gateway calls it:
 *   1. createSession()      POST /order/payments/v3/            -> tracker token
 *   2. attachMetadata()     POST /order/payments/v3/{t}/metadata
 *   3. createTimeBasedToken() POST /client/passport/v1/token    -> short-lived token
 *   4. checkoutUrl()        -> hosted page the customer is redirected to
 *
 * @see https://github.com/getsafepay/sfpy-php
 */
class SafepayClient
{
    private const ENDPOINTS = [
        'production' => 'https://api.getsafepay.com',
        'sandbox' => 'https://sandbox.api.getsafepay.com',
        'development' => 'https://dev.api.getsafepay.com',
    ];

    /**
     * The hosted checkout lives on a different host from the API. In production
     * it is the storefront host, not the API host.
     */
    private const CHECKOUT_ENDPOINTS = [
        'production' => 'https://getsafepay.com',
        'sandbox' => 'https://sandbox.api.getsafepay.com',
        'development' => 'https://dev.api.getsafepay.com',
    ];

    public function __construct(private array $config = []) {}

    public function environment(): string
    {
        $environment = (string) ($this->config['environment'] ?? 'sandbox');

        return array_key_exists($environment, self::ENDPOINTS) ? $environment : 'sandbox';
    }

    /**
     * Both keys are required: without the secret we cannot authenticate, and
     * without the public key there is no merchant to pay.
     */
    public function isConfigured(): bool
    {
        return filled($this->config['public_key'] ?? null)
            && filled($this->config['secret_key'] ?? null);
    }

    public function hasWebhookSecret(): bool
    {
        return filled($this->config['webhook_secret'] ?? null);
    }

    public function apiBase(): string
    {
        return rtrim((string) ($this->config['api_base'] ?: self::ENDPOINTS[$this->environment()]), '/');
    }

    public function checkoutBase(): string
    {
        return rtrim((string) ($this->config['checkout_base'] ?: self::CHECKOUT_ENDPOINTS[$this->environment()]), '/');
    }

    /**
     * Open a payment session and return its tracker token.
     */
    public function createSession(Order $order): array
    {
        $payload = [
            'merchant_api_key' => (string) $this->config['public_key'],
            'intent' => (string) ($this->config['intent'] ?? 'CYBERSOURCE'),
            'mode' => 'payment',
            'currency' => strtoupper((string) $order->currency),
            'amount' => $this->toMinorUnits($order->total_amount),
        ];

        return $this->post('/order/payments/v3/', $payload);
    }

    /**
     * Attach our identifiers to the session so the webhook can be matched back
     * to an order without guessing.
     */
    public function attachMetadata(string $tracker, array $data): array
    {
        return $this->post("/order/payments/v3/{$tracker}/metadata", ['data' => $data]);
    }

    /**
     * The hosted checkout requires a short-lived token minted server-side.
     */
    public function createTimeBasedToken(): array
    {
        return $this->post('/client/passport/v1/token', []);
    }

    /**
     * The hosted checkout URL the customer is sent to.
     *
     * `source` selects the hosted page's behaviour and the page switches on it
     * exhaustively — an unknown value renders no completion handler, so the
     * customer pays and is then left on SafePay's own completion page. See
     * `config/payment.php` for the values SafePay accepts.
     *
     * `order_id` is echoed back on the return URL (`?order_id=…&tracker=…`),
     * which makes the return easy to trace in logs; the authoritative match is
     * still the tracker.
     */
    public function checkoutUrl(
        string $tracker,
        string $token,
        ?string $redirectUrl,
        ?string $cancelUrl,
        ?string $orderId = null,
    ): string {
        $query = http_build_query(array_filter([
            'tbt' => $token,
            'tracker' => $tracker,
            'order_id' => $orderId,
            'environment' => $this->environment(),
            'source' => (string) ($this->config['source'] ?? 'hosted'),
            'redirect_url' => $redirectUrl,
            'cancel_url' => $cancelUrl,
        ], fn ($value) => $value !== null && $value !== ''));

        return $this->checkoutBase().'/embedded?'.$query;
    }

    /**
     * Payment state as Safepay sees it, for reconciliation and for `verify()`.
     */
    public function retrievePayment(string $tracker): array
    {
        return $this->get("/reporter/api/v2/payments/{$tracker}");
    }

    public function refund(?string $transactionId, ?int $amountMinor, ?string $reason = null): array
    {
        $payload = array_filter([
            'amount' => $amountMinor,
            'reason' => $reason,
        ], fn ($value) => $value !== null);

        if ($transactionId) {
            return $this->post("/client/transactions/v1/{$transactionId}/refund", $payload);
        }

        return $this->post('/client/transactions/v1/refund', $payload);
    }

    /**
     * Safepay takes amounts in minor units. Rounding here (rather than casting)
     * keeps 10.005 from silently truncating to 10.00.
     */
    public function toMinorUnits(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function request(): PendingRequest
    {
        $request = Http::baseUrl($this->apiBase())
            ->acceptJson()
            ->asJson()
            ->timeout((int) ($this->config['timeout'] ?? 20))
            ->withHeaders([
                'X-SFPY-MERCHANT-SECRET' => (string) $this->config['secret_key'],
            ]);

        $retries = (int) ($this->config['retries'] ?? 0);

        return $retries > 0 ? $request->retry($retries, 250, throw: false) : $request;
    }

    private function post(string $path, array $payload): array
    {
        return $this->unwrap($this->request()->post($path, $payload), $path);
    }

    private function get(string $path, array $query = []): array
    {
        return $this->unwrap($this->request()->get($path, $query), $path);
    }

    /**
     * Safepay answers with `{"data": …}` on success and `{"status":{"errors":[…]}}`
     * on failure. Normalise both into an array or a typed exception.
     *
     * `data` is not always an object: the time-based token endpoint answers
     * `{"data":"<token>"}`, a bare string. That is normalised to `['token' => …]`
     * so callers can keep reading a stable shape.
     */
    private function unwrap(Response $response, string $path): array
    {
        if ($response->successful()) {
            $body = $response->json();

            if (! is_array($body)) {
                return [];
            }

            $data = $body['data'] ?? $body;

            return is_array($data) ? $data : ['token' => $data];
        }

        $errors = (array) $response->json('status.errors');
        $message = $errors !== []
            ? implode(', ', array_map('strval', $errors))
            : 'Safepay request to '.$path.' failed with status '.$response->status();

        throw new SafepayException($message, $response->status(), (array) $response->json());
    }
}
