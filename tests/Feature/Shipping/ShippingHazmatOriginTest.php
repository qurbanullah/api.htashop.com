<?php

namespace Tests\Feature\Shipping;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\City;
use App\Models\Country;
use App\Models\Inventory;
use App\Models\Measurement;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductShipping;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\ShippingZoneLocation;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\Shipping\ShippingQuoteService;
use App\Support\Shipping\Carriers\LeopardsRateProvider;
use App\Support\Shipping\ShippingQuote;
use Database\Seeders\MeasurementSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    config([
        'shipping.enabled' => true,
        'shipping.rates.PKR' => ['flat_rate' => 999, 'free_over' => null],
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

function hoUnit(string $measurement, string $code): int
{
    $measurementId = (int) Measurement::query()->where('code', $measurement)->value('id');

    return (int) Unit::query()
        ->where('measurement_id', $measurementId)
        ->where('code', $code)
        ->value('id');
}

function hoTenant(): Tenant
{
    return Tenant::create([
        'name' => 'Origin Tenant',
        'slug' => 'origin-'.Str::lower(Str::random(8)),
        'is_active' => true,
    ]);
}

function hoOrganization(Tenant $tenant): Organization
{
    return Organization::create([
        'tenant_id' => $tenant->id,
        'name' => 'Origin Vendor',
        'slug' => 'origin-org-'.Str::lower(Str::random(8)),
        'type' => 'vendor',
        'is_active' => true,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function hoProduct(Tenant $tenant, Organization $organization, array $overrides = []): Product
{
    $product = Product::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'name' => 'Origin Widget',
        'slug' => 'origin-widget-'.Str::lower(Str::random(8)),
        'status' => 'active',
        'is_active' => true,
    ]);

    ProductShipping::create(array_merge([
        'product_id' => $product->id,
        'gross_weight' => 2,
        'weight_unit_id' => hoUnit('mass', 'kg'),
        'length' => 30,
        'width' => 20,
        'height' => 10,
        'dimension_unit_id' => hoUnit('length', 'cm'),
        'ships_alone' => false,
        'is_fragile' => false,
        'is_stackable' => true,
        'is_hazmat' => false,
    ], $overrides));

    return $product;
}

function hoCart(Product $product, int $quantity = 1): Cart
{
    $cart = Cart::create([
        'session_id' => 'origin-cart-'.Str::lower(Str::random(10)),
        'currency' => 'PKR',
        'status' => 'active',
    ]);

    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'unit_price' => 500,
        'currency' => 'PKR',
    ]);

    return $cart;
}

function hoWarehouse(Tenant $tenant, string $name, string $city, string $slug): Warehouse
{
    return Warehouse::create([
        'tenant_id' => $tenant->id,
        'name' => $name,
        'slug' => $slug,
        'city' => $city,
        'is_active' => true,
    ]);
}

function hoStock(Warehouse $warehouse, Product $product, float $quantity): Inventory
{
    return Inventory::create([
        'tenant_id' => $warehouse->tenant_id,
        'warehouse_id' => $warehouse->id,
        'stockable_type' => Product::class,
        'stockable_id' => $product->id,
        'sku' => 'STOCK-'.Str::upper(Str::random(6)),
        'quantity' => $quantity,
        'reserved' => 0,
        'is_active' => true,
    ]);
}

function hoZone(Tenant $tenant): ShippingZone
{
    $zone = ShippingZone::create([
        'tenant_id' => $tenant->id,
        'name' => 'Lahore Metro',
        'code' => 'lahore-'.Str::lower(Str::random(4)),
        'is_active' => true,
    ]);

    ShippingZoneLocation::create([
        'shipping_zone_id' => $zone->id,
        'type' => ShippingZoneLocation::TYPE_CITY,
        'country_id' => (int) Country::query()->where('code', 'PK')->value('id'),
        'city_id' => (int) City::query()->where('name', 'Lahore')->value('id'),
    ]);

    return $zone;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function hoRate(Tenant $tenant, ?ShippingZone $zone, array $attributes = []): ShippingRate
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
        'carries_hazmat' => true,
    ], $attributes));
}

function hoQuote(Cart $cart, Tenant $tenant): ShippingQuote
{
    return app(ShippingQuoteService::class)->forCart(
        $cart,
        test()->destination,
        'PKR',
        1000.0,
        null,
        $tenant->id,
    );
}

it('quotes from the warehouse with the most stock and reports it', function () {
    $tenant = hoTenant();
    $organization = hoOrganization($tenant);
    $product = hoProduct($tenant, $organization);
    $cart = hoCart($product);

    $small = hoWarehouse($tenant, 'Small DC', 'Lahore', 'small-dc');
    $big = hoWarehouse($tenant, 'Big DC', 'Karachi', 'big-dc');

    hoStock($small, $product, 3);
    hoStock($big, $product, 25);

    $zone = hoZone($tenant);
    hoRate($tenant, $zone);

    $quote = hoQuote($cart, $tenant);

    expect($quote->origin)->toBe([
        'uuid' => $big->uuid,
        'name' => 'Big DC',
        'city' => 'Karachi',
    ])
        ->and($quote->toArray()['origin']['name'])->toBe('Big DC');
});

it('skips a rate scoped to a warehouse the basket does not ship from', function () {
    $tenant = hoTenant();
    $organization = hoOrganization($tenant);
    $product = hoProduct($tenant, $organization);
    $cart = hoCart($product);

    $origin = hoWarehouse($tenant, 'Origin DC', 'Lahore', 'origin-dc');
    $other = hoWarehouse($tenant, 'Other DC', 'Islamabad', 'other-dc');

    hoStock($origin, $product, 50);

    $zone = hoZone($tenant);
    hoRate($tenant, $zone, ['name' => 'Anywhere', 'warehouse_id' => null, 'base_amount' => 300]);
    hoRate($tenant, $zone, [
        'name' => 'Other DC Express',
        'warehouse_id' => $other->id,
        'base_amount' => 200,
        'priority' => 1,
    ]);

    $quote = hoQuote($cart, $tenant);

    expect($quote->options)->toHaveCount(1)
        ->and($quote->selected?->name)->toBe('Anywhere')
        ->and($quote->origin['name'])->toBe('Origin DC');
});

it('skips rates that cannot carry dangerous goods', function () {
    $tenant = hoTenant();
    $organization = hoOrganization($tenant);
    $product = hoProduct($tenant, $organization, ['is_hazmat' => true]);
    $cart = hoCart($product);

    $warehouse = hoWarehouse($tenant, 'Hazmat DC', 'Lahore', 'hazmat-dc');
    hoStock($warehouse, $product, 10);

    $zone = hoZone($tenant);
    hoRate($tenant, $zone, ['name' => 'Standard', 'carries_hazmat' => false, 'priority' => 0]);
    hoRate($tenant, $zone, ['name' => 'Hazmat Courier', 'carries_hazmat' => true, 'priority' => 1]);

    $quote = hoQuote($cart, $tenant);

    expect($quote->options)->toHaveCount(1)
        ->and($quote->selected?->name)->toBe('Hazmat Courier');

    $flags = app(ShippingQuoteService::class)->basketFlags($cart);

    expect($flags['hazmat'])->toBeTrue();
});

it('does not ask the national carrier about a hazmat basket', function () {
    Http::fake([
        'api.leopardscourier.com/*' => Http::response(['status' => 1, 'charges' => 300], 200),
    ]);

    config(['shipping.carriers' => [
        'leopards' => [
            'driver' => LeopardsRateProvider::class,
            'enabled' => true,
            'api_key' => 'test-key',
            'api_password' => 'test-pass',
        ],
    ]]);

    $tenant = hoTenant();
    $organization = hoOrganization($tenant);
    $product = hoProduct($tenant, $organization, ['is_hazmat' => true]);
    $cart = hoCart($product);

    $warehouse = hoWarehouse($tenant, 'Hazmat DC', 'Lahore', 'hazmat-dc');
    hoStock($warehouse, $product, 10);

    $quote = hoQuote($cart, $tenant);

    Http::assertNotSent(
        fn ($request) => str_contains($request->url(), 'leopardscourier'),
    );

    // Nothing can price it, so the configured flat rate is the honest answer.
    expect($quote->source)->toBe(ShippingQuote::SOURCE_CONFIG);
});
