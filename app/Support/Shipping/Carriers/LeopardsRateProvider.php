<?php

namespace App\Support\Shipping\Carriers;

use App\Support\Shipping\Carriers\Leopards\LeopardsClient;
use App\Support\Shipping\Carriers\Leopards\LeopardsException;
use App\Support\Shipping\ShippingOption;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Live Leopards rates for the checkout quote.
 *
 * A failure here never fails the quote: the merchant-defined rates remain, and
 * the problem is logged for operations.
 */
class LeopardsRateProvider implements CarrierRateProvider
{
    protected ?LeopardsClient $client = null;

    public function __construct(
        protected array $config = [],
    ) {}

    public function code(): string
    {
        return 'leopards';
    }

    public function isConfigured(): bool
    {
        return $this->client()->isConfigured();
    }

    /**
     * @return array<int, ShippingOption>
     */
    public function quote(CarrierQuoteRequest $request): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        // Leopards is a domestic carrier quoting in PKR and does not accept
        // dangerous goods, so a hazmat basket falls back to the merchant's own
        // (hazmat-capable) rates.
        if (strtoupper($request->currency) !== 'PKR' || $request->containsHazmat) {
            return [];
        }

        $city = trim((string) $request->city);

        // The rate endpoint needs a destination city; a postal-only address
        // falls back to the merchant's own rates.
        if ($city === '') {
            return [];
        }

        $type = (string) ($this->config['shipment_type'] ?? 'overnight');

        try {
            $body = $this->client()->charges([
                'shipment_type' => $type,
                'origin_city' => (string) ($this->config['origin_city'] ?? 'Lahore'),
                'destination_city' => $city,
                'weight' => round($request->weightKg, 3),
                'cod_amount' => 0,
                'shipment_booking_date' => now()->format('Y-m-d'),
            ]);
        } catch (LeopardsException $exception) {
            Log::warning('Leopards rate lookup failed', [
                'error' => $exception->getMessage(),
            ]);

            return [];
        }

        $amount = $this->extractAmount($body);

        if ($amount === null || $amount <= 0) {
            Log::warning('Leopards returned no usable charge', ['payload_keys' => array_keys($body)]);

            return [];
        }

        $slug = Str::slug($type);

        return [new ShippingOption(
            id: 'leopards:'.$slug,
            name: 'Leopards '.Str::headline($type),
            code: $slug,
            carrier: 'leopards',
            amount: round($amount, 2),
            currency: 'PKR',
            free: false,
            estimatedDaysMin: $this->days('estimated_days_min', 2),
            estimatedDaysMax: $this->days('estimated_days_max', 4),
        )];
    }

    /**
     * The charges endpoint has appeared under a few shapes; accept the ones we
     * have seen rather than binding the integration to a single field.
     *
     * @param  array<string, mixed>  $body
     */
    private function extractAmount(array $body): ?float
    {
        $candidates = [
            data_get($body, 'charges.charges'),
            data_get($body, 'charges.total_charges'),
            data_get($body, 'charges.total'),
            data_get($body, 'data.charges'),
            data_get($body, 'data.total_charges'),
            data_get($body, 'total_charges'),
            data_get($body, 'charges'),
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate)) {
                return (float) $candidate;
            }
        }

        return null;
    }

    private function days(string $key, int $default): ?int
    {
        $value = (int) ($this->config[$key] ?? $default);

        return $value > 0 ? $value : null;
    }

    private function client(): LeopardsClient
    {
        return $this->client ??= new LeopardsClient($this->config);
    }
}
