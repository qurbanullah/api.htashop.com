<?php

namespace Tests\Feature\Shipping;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\City;
use App\Models\Country;
use App\Models\Measurement;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductShipping;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\ShippingZoneLocation;
use App\Models\Tenant;
use App\Models\Unit;
use App\Services\Shipping\ShippingQuoteService;
use App\Support\Shipping\Carriers\LeopardsRateProvider;
use App\Support\Shipping\ShippingQuote;
use Database\Seeders\MeasurementSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config([
        'shipping.enabled' => true,
        'shipping.rates.PKR' => ['flat_rate' => 999, 'free_over' => null],
        'shipping.rates.USD' => ['flat_rate' => 10, 'free_over' => null],
        'shipping.default' => ['flat_rate' => 0, 'free_over' => null],
        'shipping.volumetric_divisor' => 5000,
        'shipping.carriers' => [],
    ]);

    $this->seed(MeasurementSeeder::class);

    $this->country = Country::query()->firstOrCreate(
        ['code' => 'PK'],
        ['name' => 'Pakistan', 'code_alpha3' => 'PAK'],
    );
    $this->city = City::create(['country_id' => $this->country->id, 'name' => 'Lahore']);
    $this->destination = ['country_id' => $this->country->id, 'city_id' => $this->city->id];
});

function sqUnit(string $measurement, string $code): int
{
    $measurementId = (int) Measurement::query()->where('code', $measurement)->value('id');

    return (int) Unit::query()
        ->where('measurement_id', $measurementId)
        ->where('code', $code)
        ->value('id');
}

function sqTenant(): Tenant
{
    return Tenant::create([
        'name' => 'Shipping Tenant',
        'slug' => 'ship-'.Str::lower(Str::random(8)),
        'is_active' => true,
    ]);
}

function sqOrganization(Tenant $tenant): Organization
{
    return Organization::create([
        'tenant_id' => $tenant->id,
        'name' => 'Shipping Vendor',
        'slug' => 'ship-org-'.Str::lower(Str::random(8)),
        'type' => 'vendor',
        'is_active' => true,
    ]);
}

/**
 * @param  array<string, mixed>  $shipping
 */
function sqProduct(Tenant $tenant, Organization $organization, array $shipping = []): Product
{
    $product = Product::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'name' => 'Shipping Widget',
        'slug' => 'ship-widget-'.Str::lower(Str::random(8)),
        'status' => 'active',
        'is_active' => true,
        'metadata' => ['price' => 500, 'currency' => 'PKR'],
    ]);

    ProductShipping::create(array_merge([
        'product_id' => $product->id,
        'net_weight' => 1.8,
        'gross_weight' => 2,
        'weight_unit_id' => sqUnit('mass', 'kg'),
        'length' => 30,
        'width' => 20,
        'height' => 10,
        'dimension_unit_id' => sqUnit('length', 'cm'),
        'units_per_case' => 1,
        'ships_alone' => false,
        'is_fragile' => false,
        'is_stackable' => true,
        'is_hazmat' => false,
    ], $shipping));

    return $product;
}

function sqCart(Product $product, int $quantity = 1, string $currency = 'PKR'): Cart
{
    $cart = Cart::create([
        'session_id' => 'ship-cart-'.Str::lower(Str::random(10)),
        'currency' => $currency,
        'status' => 'active',
    ]);

    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'unit_price' => 500,
        'currency' => $currency,
    ]);

    return $cart;
}

function sqZone(Tenant $tenant, string $name, array $types = ['city']): ShippingZone
{
    $country = Country::query()->where('code', 'PK')->firstOrFail();
    $city = City::query()->where('country_id', $country->id)->firstOrFail();

    $zone = ShippingZone::create([
        'tenant_id' => $tenant->id,
        'name' => $name,
        'code' => Str::slug($name).'-'.Str::lower(Str::random(4)),
        'is_active' => true,
    ]);

    foreach ($types as $type) {
        ShippingZoneLocation::create([
            'shipping_zone_id' => $zone->id,
            'type' => $type,
            'country_id' => $country->id,
            'city_id' => $type === ShippingZoneLocation::TYPE_CITY ? $city->id : null,
        ]);
    }

    return $zone;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function sqRate(Tenant $tenant, ?ShippingZone $zone, array $attributes = []): ShippingRate
{
    return ShippingRate::create(array_merge([
        'tenant_id' => $tenant->id,
        'shipping_zone_id' => $zone?->id,
        'name' => 'Standard',
        'code' => 'standard-'.Str::lower(Str::random(4)),
        'carrier' => ShippingRate::CARRIER_MANUAL,
        'calculation' => ShippingRate::CALC_FLAT,
        'currency' => 'PKR',
        'base_amount' => 250,
        'priority' => 0,
        'is_active' => true,
    ], $attributes));
}

function sqQuote(Cart $cart, ?array $destination = null, float $subtotal = 1000.0, ?string $preferred = null, ?Tenant $tenant = null, bool $strict = false): ShippingQuote
{
    return app(ShippingQuoteService::class)->forCart(
        $cart,
        $destination ?? test()->destination,
        'PKR',
        $subtotal,
        $preferred,
        $tenant?->id,
        $strict,
    );
}

it('prices delivery with the most specific zone', function () {
    $tenant = sqTenant();
    $organization = sqOrganization($tenant);
    $cart = sqCart(sqProduct($tenant, $organization));

    $metro = sqZone($tenant, 'Lahore Metro');
    sqRate($tenant, $metro, ['base_amount' => 250]);

    $province = sqZone($tenant, 'Pakistan', ['country']);
    sqRate($tenant, $province, ['base_amount' => 500]);

    $quote = sqQuote($cart, tenant: $tenant);

    expect($quote->zone['name'])->toBe('Lahore Metro')
        ->and($quote->selected?->name)->toBe('Standard')
        ->and($quote->fee())->toBe(250.0)
        ->and($quote->source)->toBe(ShippingQuote::SOURCE_RATES);
});

it('charges the per-kilo amount above the included weight', function () {
    $tenant = sqTenant();
    $organization = sqOrganization($tenant);
    $cart = sqCart(sqProduct($tenant, $organization), 2);

    $zone = sqZone($tenant, 'Lahore Metro');
    sqRate($tenant, $zone, [
        'calculation' => ShippingRate::CALC_PER_WEIGHT,
        'base_amount' => 200,
        'included_weight_kg' => 1,
        'per_kg_amount' => 100,
    ]);

    $quote = sqQuote($cart, tenant: $tenant);

    // 2 × 2 kg = 4 kg billable; 200 + (4 − 1) × 100.
    expect($quote->parcel->billableWeightKg)->toBe(4.0)
        ->and($quote->fee())->toBe(500.0);
});

it('selects the matching weight bracket of a tiered rate', function () {
    $tenant = sqTenant();
    $organization = sqOrganization($tenant);
    $cart = sqCart(sqProduct($tenant, $organization), 2);

    $zone = sqZone($tenant, 'Lahore Metro');
    $rate = sqRate($tenant, $zone, ['calculation' => ShippingRate::CALC_TIERED]);

    $rate->tiers()->createMany([
        ['min_weight_kg' => 0, 'max_weight_kg' => 2, 'amount' => 250],
        ['min_weight_kg' => 2, 'max_weight_kg' => 5, 'amount' => 400],
        ['min_weight_kg' => 5, 'max_weight_kg' => null, 'amount' => 600],
    ]);

    $quote = sqQuote($cart, tenant: $tenant);

    // 4 kg lands in the 2–5 kg bracket.
    expect($quote->fee())->toBe(400.0);
});

it('skips a rate whose weight window does not cover the parcel', function () {
    $tenant = sqTenant();
    $organization = sqOrganization($tenant);
    $cart = sqCart(sqProduct($tenant, $organization), 2);

    $zone = sqZone($tenant, 'Lahore Metro');
    sqRate($tenant, $zone, ['base_amount' => 150, 'max_weight_kg' => 1]);
    sqRate($tenant, $zone, ['base_amount' => 300]);

    $quote = sqQuote($cart, tenant: $tenant);

    expect($quote->options)->toHaveCount(1)
        ->and($quote->fee())->toBe(300.0);
});

it('uses volumetric weight when it exceeds the actual weight', function () {
    $tenant = sqTenant();
    $organization = sqOrganization($tenant);

    $product = sqProduct($tenant, $organization, [
        'length' => 50,
        'width' => 50,
        'height' => 50,
    ]);
    $cart = sqCart($product);

    $zone = sqZone($tenant, 'Lahore Metro');
    sqRate($tenant, $zone, [
        'calculation' => ShippingRate::CALC_PER_WEIGHT,
        'base_amount' => 0,
        'included_weight_kg' => 0,
        'per_kg_amount' => 10,
    ]);

    $quote = sqQuote($cart, tenant: $tenant);

    // 50 × 50 × 50 cm ÷ 5000 = 25 kg volumetric.
    expect($quote->parcel->volumetricWeightKg)->toBe(25.0)
        ->and($quote->parcel->billableWeightKg)->toBe(25.0)
        ->and($quote->fee())->toBe(250.0);
});

it('makes an option free above its free-shipping threshold', function () {
    $tenant = sqTenant();
    $organization = sqOrganization($tenant);
    $cart = sqCart(sqProduct($tenant, $organization));

    $zone = sqZone($tenant, 'Lahore Metro');
    sqRate($tenant, $zone, ['base_amount' => 250, 'free_over_amount' => 500]);

    $quote = sqQuote($cart, subtotal: 1000.0, tenant: $tenant);

    expect($quote->fee())->toBe(0.0)
        ->and($quote->selected?->free)->toBeTrue()
        ->and($quote->freeShippingThreshold)->toBe(500.0);
});

it('falls back to the legacy flat rate when nothing is configured', function () {
    $tenant = sqTenant();
    $organization = sqOrganization($tenant);
    $cart = sqCart(sqProduct($tenant, $organization));

    $quote = sqQuote($cart, tenant: $tenant);

    expect($quote->source)->toBe(ShippingQuote::SOURCE_CONFIG)
        ->and($quote->selected?->id)->toBe('config:pkr')
        ->and($quote->fee())->toBe(999.0);
});

it('honours a preferred option and defaults to the first otherwise', function () {
    $tenant = sqTenant();
    $organization = sqOrganization($tenant);
    $cart = sqCart(sqProduct($tenant, $organization));

    $zone = sqZone($tenant, 'Lahore Metro');
    $cheap = sqRate($tenant, $zone, ['name' => 'Economy', 'base_amount' => 200, 'priority' => 1]);
    $express = sqRate($tenant, $zone, ['name' => 'Express', 'base_amount' => 300, 'priority' => 0]);

    $preferred = sqQuote($cart, preferred: $express->uuid, tenant: $tenant);
    expect($preferred->selected?->name)->toBe('Express')
        ->and($preferred->fee())->toBe(300.0);

    $default = sqQuote($cart, tenant: $tenant);
    expect($default->selected?->name)->toBe('Express');

    // A stale id is ignored on a preview…
    $stale = sqQuote($cart, preferred: (string) Str::uuid(), tenant: $tenant);
    expect($stale->selected?->name)->toBe('Express');

    // …but is a hard failure at checkout.
    expect(fn () => sqQuote($cart, preferred: (string) Str::uuid(), tenant: $tenant, strict: true))
        ->toThrow(ValidationException::class);

    // Keep the cheap rate referenced so the intent of the ordering stays clear.
    expect($cheap->uuid)->not->toBe($express->uuid);
});

it('offers flat rates when the parcel weight is unknown', function () {
    $tenant = sqTenant();
    $organization = sqOrganization($tenant);

    $product = Product::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'name' => 'Unweighed Widget',
        'slug' => 'unweighed-'.Str::lower(Str::random(8)),
        'status' => 'active',
        'is_active' => true,
        'metadata' => ['price' => 500, 'currency' => 'PKR'],
    ]);
    $cart = sqCart($product);

    $zone = sqZone($tenant, 'Lahore Metro');
    sqRate($tenant, $zone, ['name' => 'Flat', 'base_amount' => 300]);
    sqRate($tenant, $zone, [
        'name' => 'By Weight',
        'calculation' => ShippingRate::CALC_PER_WEIGHT,
        'base_amount' => 100,
        'per_kg_amount' => 50,
    ]);

    $quote = sqQuote($cart, tenant: $tenant);

    expect($quote->parcel->weightComplete)->toBeFalse()
        ->and($quote->options)->toHaveCount(1)
        ->and($quote->selected?->name)->toBe('Flat')
        ->and($quote->fee())->toBe(300.0);
});

it('ignores a carrier provider that has no credentials', function () {
    config(['shipping.carriers' => [
        'leopards' => [
            'driver' => LeopardsRateProvider::class,
            'enabled' => true,
            'api_key' => null,
        ],
    ]]);

    $tenant = sqTenant();
    $organization = sqOrganization($tenant);
    $cart = sqCart(sqProduct($tenant, $organization));

    $quote = sqQuote($cart, tenant: $tenant);

    expect($quote->source)->toBe(ShippingQuote::SOURCE_CONFIG)
        ->and($quote->options)->toHaveCount(1);
});

it('keeps merchant rates when a configured carrier returns nothing', function () {
    Http::fake([
        'api.leopardscourier.com/*' => Http::response(['status' => 1], 200),
    ]);

    config(['shipping.carriers' => [
        'leopards' => [
            'driver' => LeopardsRateProvider::class,
            'enabled' => true,
            'api_key' => 'test-key',
            'api_password' => 'test-pass',
        ],
    ]]);

    $tenant = sqTenant();
    $organization = sqOrganization($tenant);
    $cart = sqCart(sqProduct($tenant, $organization));

    $zone = sqZone($tenant, 'Lahore Metro');
    sqRate($tenant, $zone, ['base_amount' => 250]);

    $quote = sqQuote($cart, tenant: $tenant);

    // The stub contributes no options, so the merchant rate still prices it.
    expect($quote->source)->toBe(ShippingQuote::SOURCE_RATES)
        ->and($quote->selected?->carrier)->toBe(ShippingRate::CARRIER_MANUAL)
        ->and($quote->fee())->toBe(250.0);
});
