<?php

namespace App\Services\Shipping;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductShipping;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\VariantShipping;
use App\Models\Warehouse;
use App\Support\Shipping\Carriers\CarrierQuoteRequest;
use App\Support\Shipping\Carriers\CarrierRateManager;
use App\Support\Shipping\ParcelSummary;
use App\Support\Shipping\ShippingOption;
use App\Support\Shipping\ShippingQuote;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Prices delivery for a basket.
 *
 * Resolution order:
 *   1. Merchant-defined `shipping_rates` that match the destination zone and
 *      the parcel's billable weight.
 *   2. Enabled carrier providers (Leopards is a stub until Phase 4).
 *   3. The legacy `config/shipping.php` flat rate, so an installation with no
 *      zones configured keeps behaving exactly as before.
 *
 * Everything is server-side: the client may only choose *which* returned option
 * it wants, never what it costs.
 */
class ShippingQuoteService
{
    public function __construct(
        protected ShippingZoneResolver $zones,
        protected CarrierRateManager $carriers,
        protected ShippingOriginResolver $origins,
    ) {}

    /**
     * @param  array<string, mixed>|null  $destination  Resolved shipping address snapshot
     * @param  bool  $strictSelection  Checkout passes true so a stale option is a
     *                                 hard 422 instead of a silent re-price.
     */
    public function forCart(
        Cart $cart,
        ?array $destination,
        string $currency,
        float $subtotal,
        ?string $preferredRateUuid = null,
        ?int $tenantId = null,
        bool $strictSelection = false,
    ): ShippingQuote {
        $parcel = $this->summarise($cart);
        $currency = strtoupper($currency);

        if (! config('shipping.enabled', true)) {
            return ShippingQuote::disabled($parcel);
        }

        $origin = $this->origins->forCart($cart);
        $flags = $this->basketFlags($cart);

        $zone = $this->zones->resolve($destination, $tenantId);

        $rateOptions = $this->rateOptions(
            $zone,
            $tenantId,
            $currency,
            $parcel,
            $subtotal,
            $origin,
            $flags['hazmat'],
        );
        $carrierOptions = $this->carrierOptions(
            $destination,
            $parcel,
            $currency,
            $subtotal,
            $flags['hazmat'],
        );

        $options = array_merge($rateOptions, $carrierOptions);

        if ($options === []) {
            return $this->configFallback($parcel, $subtotal, $currency, $origin);
        }

        $selected = $this->select($options, $preferredRateUuid, $strictSelection);

        return new ShippingQuote(
            options: $options,
            selected: $selected,
            zone: $zone ? [
                'uuid' => $zone->uuid,
                'name' => $zone->name,
                'code' => $zone->code,
            ] : null,
            parcel: $parcel,
            source: $rateOptions !== []
                ? ShippingQuote::SOURCE_RATES
                : ShippingQuote::SOURCE_CARRIER,
            freeShippingThreshold: $selected->freeOverAmount,
            origin: $this->originPayload($origin),
        );
    }

    /**
     * Dangerous-goods information about the basket, used to keep incompatible
     * services out of the quote.
     *
     * @return array{hazmat: bool, hazard_classes: array<int, int>}
     */
    public function basketFlags(Cart $cart): array
    {
        $items = $cart->items()->with(['product.shipping'])->get();

        $hazmat = false;
        $classes = [];

        foreach ($items as $item) {
            $shipping = $item->product?->shipping;

            if (! $shipping?->is_hazmat) {
                continue;
            }

            $hazmat = true;

            if ($shipping->hazard_class_option_id !== null) {
                $classes[(int) $shipping->hazard_class_option_id] = true;
            }
        }

        return [
            'hazmat' => $hazmat,
            'hazard_classes' => array_keys($classes),
        ];
    }

    /**
     * @return ShippingOption[]
     */
    private function rateOptions(
        ?ShippingZone $zone,
        ?int $tenantId,
        string $currency,
        ParcelSummary $parcel,
        float $subtotal,
        ?Warehouse $origin,
        bool $containsHazmat,
    ): array {
        $rates = ShippingRate::query()
            ->with('tiers')
            ->where('is_active', true)
            ->where('currency', $currency)
            ->where(function ($query) use ($tenantId) {
                $query->whereNull('tenant_id');

                if ($tenantId !== null) {
                    $query->orWhere('tenant_id', $tenantId);
                }
            })
            ->where(function ($query) use ($zone) {
                $query->whereNull('shipping_zone_id');

                if ($zone !== null) {
                    $query->orWhere('shipping_zone_id', $zone->id);
                }
            })
            ->orderBy('priority')
            ->orderBy('base_amount')
            ->orderBy('id')
            ->get();

        $options = [];

        foreach ($rates as $rate) {
            if (! $this->rateMatchesOrigin($rate, $origin)
                || (! $rate->carries_hazmat && $containsHazmat)) {
                continue;
            }

            $amount = $rate->amountFor($parcel->billableWeightKg);

            if ($amount === null) {
                continue;
            }

            $free = $rate->isFreeOver($subtotal);

            $options[] = new ShippingOption(
                id: $rate->uuid,
                name: $rate->name,
                code: $rate->code,
                carrier: $rate->carrier,
                amount: $free ? 0.0 : $amount,
                currency: $currency,
                free: $free,
                estimatedDaysMin: $rate->estimated_days_min,
                estimatedDaysMax: $rate->estimated_days_max,
                freeOverAmount: $rate->free_over_amount !== null
                    ? (float) $rate->free_over_amount
                    : null,
            );
        }

        return $options;
    }

    /** A rate scoped to another warehouse cannot serve this basket. */
    private function rateMatchesOrigin(ShippingRate $rate, ?Warehouse $origin): bool
    {
        if ($rate->warehouse_id === null) {
            return true;
        }

        return $origin !== null && (int) $rate->warehouse_id === (int) $origin->id;
    }

    /**
     * @param  array<string, mixed>|null  $destination
     * @return ShippingOption[]
     */
    private function carrierOptions(
        ?array $destination,
        ParcelSummary $parcel,
        string $currency,
        float $subtotal,
        bool $containsHazmat,
    ): array {
        $providers = $this->carriers->active();

        if ($providers === []) {
            return [];
        }

        $request = new CarrierQuoteRequest(
            weightKg: $parcel->billableWeightKg,
            lengthCm: $parcel->lengthCm,
            widthCm: $parcel->widthCm,
            heightCm: $parcel->heightCm,
            currency: $currency,
            subtotal: $subtotal,
            itemCount: $parcel->itemCount,
            city: data_get($destination, 'city'),
            postalCode: data_get($destination, 'postal_code'),
            containsHazmat: $containsHazmat,
        );

        $options = [];

        foreach ($providers as $provider) {
            try {
                array_push($options, ...$provider->quote($request));
            } catch (Throwable $exception) {
                // A courier outage must never fail the checkout: log it and keep
                // the merchant-defined options.
                Log::warning('Carrier rate lookup failed', [
                    'carrier' => $provider->code(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $options;
    }

    /**
     * @param  ShippingOption[]  $options
     */
    private function select(array $options, ?string $preferredRateUuid, bool $strict): ShippingOption
    {
        if ($preferredRateUuid !== null && $preferredRateUuid !== '') {
            foreach ($options as $option) {
                if ($option->matches($preferredRateUuid)) {
                    return $option;
                }
            }

            if ($strict) {
                throw ValidationException::withMessages([
                    'shipping_rate_uuid' => 'The selected delivery option is no longer available.',
                ]);
            }
        }

        // Rates are ordered by the merchant's priority; the first is the default.
        return $options[0];
    }

    private function configFallback(
        ParcelSummary $parcel,
        float $subtotal,
        string $currency,
        ?Warehouse $origin = null,
    ): ShippingQuote {
        $config = config("shipping.rates.{$currency}") ?? config('shipping.default', []);

        $threshold = $this->nullableFloat(data_get($config, 'free_over'));
        $free = $threshold !== null && $subtotal >= $threshold;
        $amount = $free
            ? 0.0
            : round(max(0.0, (float) data_get($config, 'flat_rate', 0)), 2);

        $option = new ShippingOption(
            id: 'config:'.strtolower($currency),
            name: 'Standard delivery',
            code: 'standard',
            carrier: 'config',
            amount: $amount,
            currency: $currency,
            free: $free,
            freeOverAmount: $threshold,
        );

        return new ShippingQuote(
            options: [$option],
            selected: $option,
            zone: null,
            parcel: $parcel,
            source: ShippingQuote::SOURCE_CONFIG,
            freeShippingThreshold: $threshold,
            origin: $this->originPayload($origin),
        );
    }

    /**
     * @return array{uuid: string, name: string, city: string|null}|null
     */
    private function originPayload(?Warehouse $origin): ?array
    {
        if ($origin === null) {
            return null;
        }

        return [
            'uuid' => $origin->uuid,
            'name' => $origin->name,
            'city' => $origin->city,
        ];
    }

    /**
     * Roll the basket up into one parcel envelope.
     *
     * Weight is additive. Dimensions are a conservative envelope until Phase 5
     * bin packing: the widest footprint, with stackable item heights summed and
     * non-stackable heights taken at their maximum.
     */
    public function summarise(Cart $cart): ParcelSummary
    {
        $items = $cart->items()
            ->with(['product.shipping', 'variant.shipping'])
            ->get();

        $divisor = (float) config('shipping.volumetric_divisor', 5000);

        if ($divisor <= 0) {
            $divisor = 5000;
        }

        $actual = 0.0;
        $complete = true;
        $count = 0;
        $length = null;
        $width = null;
        $stackedHeight = 0.0;
        $rigidHeight = 0.0;
        $sawDimensions = false;

        foreach ($items as $item) {
            $quantity = max(1, (int) $item->quantity);
            $count += $quantity;

            $weightSource = $this->weightSource($item);

            if ($weightSource === null || $weightSource->gross_weight_kg === null) {
                $complete = false;
            } else {
                $actual += (float) $weightSource->gross_weight_kg * $quantity;
            }

            $dimensions = $this->dimensionSource($item);

            if ($dimensions === null) {
                continue;
            }

            $sawDimensions = true;
            $length = max($length ?? 0.0, (float) $dimensions->length_cm);
            $width = max($width ?? 0.0, (float) $dimensions->width_cm);

            if ($item->product?->shipping?->is_stackable === false) {
                $rigidHeight = max($rigidHeight, (float) $dimensions->height_cm);
            } else {
                $stackedHeight += (float) $dimensions->height_cm * $quantity;
            }
        }

        $height = $sawDimensions ? max($stackedHeight, $rigidHeight) : null;

        $volumetric = $height !== null && $length !== null && $width !== null
            ? ($length * $width * $height) / $divisor
            : 0.0;

        return new ParcelSummary(
            actualWeightKg: $actual,
            volumetricWeightKg: $volumetric,
            // An incomplete basket cannot be priced by weight: quoting a rate on
            // a partial total would undercharge and never surface at checkout.
            billableWeightKg: $complete ? max($actual, $volumetric) : null,
            weightComplete: $complete,
            itemCount: $count,
            lengthCm: $length,
            widthCm: $width,
            heightCm: $height,
        );
    }

    private function weightSource(CartItem $item): ProductShipping|VariantShipping|null
    {
        $variant = $item->variant?->shipping;

        if ($variant !== null && $variant->gross_weight_kg !== null) {
            return $variant;
        }

        return $item->product?->shipping;
    }

    private function dimensionSource(CartItem $item): ProductShipping|VariantShipping|null
    {
        $variant = $item->variant?->shipping;

        if ($variant !== null
            && $variant->length_cm !== null
            && $variant->width_cm !== null
            && $variant->height_cm !== null) {
            return $variant;
        }

        $product = $item->product?->shipping;

        if ($product !== null
            && $product->length_cm !== null
            && $product->width_cm !== null
            && $product->height_cm !== null) {
            return $product;
        }

        return null;
    }

    /** Config values arrive as strings from env; `0` and `''` mean "no threshold". */
    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '' || (float) $value <= 0) {
            return null;
        }

        return (float) $value;
    }
}
