<?php

namespace App\Support\Shipping\Carriers\Leopards;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP client for the Leopards Courier API.
 *
 * Endpoint paths and the `api_key` / `api_password` credentials are kept in
 * `config/shipping.php`, so a contract change is a configuration change. The
 * API is form-encoded and signals success with `status: 1`; anything else is a
 * rejection we surface to the caller.
 *
 * Credentials are never logged.
 */
class LeopardsClient
{
    public function __construct(
        protected array $config = [],
    ) {}

    public function isConfigured(): bool
    {
        return (bool) ($this->config['enabled'] ?? false)
            && filled($this->config['api_key'] ?? null)
            && filled($this->config['api_password'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function charges(array $payload): array
    {
        return $this->post($this->path('rate_path', 'getShipmentCharges/format/json'), $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function book(array $payload): array
    {
        return $this->post($this->path('book_path', 'bookPacket/format/json'), $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function cancel(array $payload): array
    {
        return $this->post($this->path('cancel_path', 'cancelBookedPacket/format/json'), $payload);
    }

    private function path(string $key, string $default): string
    {
        $path = trim((string) ($this->config[$key] ?? $default), '/');

        return $path !== '' ? $path : $default;
    }

    private function url(string $path): string
    {
        $base = rtrim((string) ($this->config['api_base'] ?? 'https://api.leopardscourier.com/api'), '/');

        return $base.'/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        if (! $this->isConfigured()) {
            throw new LeopardsException('Leopards is not configured.');
        }

        try {
            $response = Http::timeout((int) ($this->config['timeout'] ?? 10))
                ->retry((int) ($this->config['retries'] ?? 0), 200, throw: false)
                ->acceptJson()
                ->asForm()
                ->post($this->url($path), array_merge($payload, [
                    'api_key' => (string) $this->config['api_key'],
                    'api_password' => (string) $this->config['api_password'],
                ]));
        } catch (ConnectionException $exception) {
            throw new LeopardsException(
                'Could not reach Leopards: '.$exception->getMessage(),
                0,
                $exception,
            );
        }

        if ($response->failed()) {
            Log::warning('Leopards request failed', [
                'path' => $path,
                'status' => $response->status(),
            ]);

            throw new LeopardsException("Leopards returned HTTP {$response->status()}.");
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new LeopardsException('Leopards returned an unexpected payload.');
        }

        if ((int) ($body['status'] ?? 1) !== 1) {
            throw new LeopardsException(
                (string) ($body['error'] ?? $body['message'] ?? 'Leopards rejected the request.'),
            );
        }

        return $body;
    }
}
