<?php

namespace Tests\Feature\Checkout;

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
use Database\Seeders\MeasurementSeeder;
use Illuminate\Support\Str;

/**
 * Phase 2 wiring: a configured zone/rate prices delivery, and the option the
 * customer picked is what the order stores. The client never sends money.
 */
beforeEach(function () {
    config([
        'shipping.enabled' => true,
        // Deliberately different from the configured rate so the assertion can
        // only pass if the zone rate was used.
        'shipping.rates.PKR' => ['flat_rate' => 999, 'free_over' => null],
        'shipping.rates.USD' => ['flat_rate' => 10, 'free_over' => null],
        'shipping.default' => ['flat_rate' => 0, 'free_over' => null],
        'shipping.tax.enabled' => false,
        'shipping.tax.rate' => 0,
        'shipping.volumetric_divisor' => 5000,
        'shipping.carriers' => [],
    ]);

    // Create the tenant first: `MeasurementSeeder` auto-creates a default tenant
    // when none exists, which would become the "first tenant" checkout resolves.
    $this->tenant = Tenant::create([
        'name' => 'Checkout Tenant',
        'slug' => 'cks-tenant-'.Str::lower(Str::random(6)),
        'is_active' => true,
    ]);

    $this->seed(MeasurementSeeder::class);

    $this->country = Country::query()->firstOrCreate(
        ['code' => 'PK'],
        ['name' => 'Pakistan', 'code_alpha3' => 'PAK'],
    );
    $this->city = City::create(['country_id' => $this->country->id, 'name' => 'Lahore']);

    $this->organization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Checkout Vendor',
        'slug' => 'cks-org-'.Str::lower(Str::random(6)),
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $this->product = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'name' => 'Checkout Widget',
        'slug' => 'cks-widget-'.Str::lower(Str::random(6)),
        'status' => 'active',
        'is_active' => true,
        'metadata' => ['price' => 1000, 'currency' => 'PKR'],
    ]);

    ProductShipping::create([
        'product_id' => $this->product->id,
        'net_weight' => 1.8,
        'gross_weight' => 2,
        'weight_unit_id' => cksUnit('mass', 'kg'),
        'length' => 30,
        'width' => 20,
        'height' => 10,
        'dimension_unit_id' => cksUnit('length', 'cm'),
        'units_per_case' => 1,
        'is_stackable' => true,
    ]);

    $this->cart = Cart::create([
        'session_id' => 'cks-cart-'.Str::lower(Str::random(8)),
        'currency' => 'PKR',
        'status' => 'active',
    ]);

    CartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => 1000,
        'currency' => 'PKR',
    ]);

    $this->cityZone = ShippingZone::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Lahore Metro',
        'code' => 'lahore-metro',
        'is_active' => true,
    ]);

    ShippingZoneLocation::create([
        'shipping_zone_id' => $this->cityZone->id,
        'type' => ShippingZoneLocation::TYPE_CITY,
        'country_id' => $this->country->id,
        'city_id' => $this->city->id,
    ]);

    $this->cityRate = ShippingRate::create([
        'tenant_id' => $this->tenant->id,
        'shipping_zone_id' => $this->cityZone->id,
        'name' => 'Standard',
        'code' => 'standard',
        'carrier' => ShippingRate::CARRIER_MANUAL,
        'calculation' => ShippingRate::CALC_FLAT,
        'currency' => 'PKR',
        'base_amount' => 400,
        'priority' => 0,
        'is_active' => true,
    ]);

    $this->countryZone = ShippingZone::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Pakistan',
        'code' => 'pakistan',
        'is_active' => true,
    ]);

    ShippingZoneLocation::create([
        'shipping_zone_id' => $this->countryZone->id,
        'type' => ShippingZoneLocation::TYPE_COUNTRY,
        'country_id' => $this->country->id,
    ]);

    ShippingRate::create([
        'tenant_id' => $this->tenant->id,
        'shipping_zone_id' => $this->countryZone->id,
        'name' => 'Nationwide',
        'code' => 'nationwide',
        'carrier' => ShippingRate::CARRIER_MANUAL,
        'calculation' => ShippingRate::CALC_FLAT,
        'currency' => 'PKR',
        'base_amount' => 600,
        'priority' => 0,
        'is_active' => true,
    ]);
});

function cksUnit(string $measurement, string $code): int
{
    $measurementId = (int) Measurement::query()->where('code', $measurement)->value('id');

    return (int) Unit::query()
        ->where('measurement_id', $measurementId)
        ->where('code', $code)
        ->value('id');
}

/**
 * @return array<string, mixed>
 */
function cksDestination(Country $country, City $city): array
{
    return [
        'contact_name' => 'Test Customer',
        'phone' => '+923001234567',
        'address_line_1' => '263 Nishtar Block',
        'city' => 'Lahore',
        'postal_code' => '54570',
        'country_id' => $country->id,
        'city_id' => $city->id,
    ];
}

it('quotes the zone rate for the destination', function () {
    $response = $this->withHeader('X-Cart-Token', $this->cart->session_id)
        ->postJson('/api/v1/checkout/quote', [
            'shipping_address' => cksDestination($this->country, $this->city),
        ])
        ->assertOk();

    expect((float) $response->json('data.shipping_fee'))->toBe(400.0)
        ->and($response->json('data.shipping_method'))->toBe('Standard')
        ->and($response->json('data.shipping_source'))->toBe('rates')
        // A destination belongs to exactly one zone, so the nationwide rate in
        // the less specific zone is not offered alongside the metro rate.
        ->and($response->json('data.shipping_options'))->toHaveCount(1)
        ->and((float) $response->json('data.total'))->toBe(1400.0);
});

it('falls back to the country zone for a city without its own zone', function () {
    $karachi = City::create(['country_id' => $this->country->id, 'name' => 'Karachi']);

    $response = $this->withHeader('X-Cart-Token', $this->cart->session_id)
        ->postJson('/api/v1/checkout/quote', [
            'shipping_address' => cksDestination($this->country, $karachi),
        ])
        ->assertOk();

    expect((float) $response->json('data.shipping_fee'))->toBe(600.0)
        ->and($response->json('data.shipping_method'))->toBe('Nationwide');
});

it('keeps the configured flat rate when no destination is supplied', function () {
    $response = $this->withHeader('X-Cart-Token', $this->cart->session_id)
        ->postJson('/api/v1/checkout/quote')
        ->assertOk();

    expect($response->json('data.shipping_source'))->toBe('config')
        ->and((float) $response->json('data.shipping_fee'))->toBe(999.0)
        ->and($response->json('data.shipping_rate_uuid'))->toBeNull();
});

it('charges the selected rate and records it on the order', function () {
    $response = $this->withHeader('X-Cart-Token', $this->cart->session_id)
        ->postJson('/api/v1/checkout', [
            'payment_method' => 'cod',
            'customer_name' => 'Test Customer',
            'customer_email' => 'buyer@example.test',
            'customer_phone' => '+923001234567',
            'shipping_address' => cksDestination($this->country, $this->city),
            'shipping_rate_uuid' => $this->cityRate->uuid,
            // A crafted fee must be ignored.
            'shipping_fee' => 0,
        ])
        ->assertCreated();

    $order = $response->json('data.order');

    expect((float) $order['shipping_fee'])->toBe(400.0)
        ->and($order['shipping_method'])->toBe('Standard')
        ->and($order['shipping_rate_uuid'])->toBe($this->cityRate->uuid)
        ->and((float) $order['total_amount'])->toBe(1400.0);
});

it('rejects a selected option that no longer exists', function () {
    $this->withHeader('X-Cart-Token', $this->cart->session_id)
        ->postJson('/api/v1/checkout', [
            'payment_method' => 'cod',
            'shipping_address' => cksDestination($this->country, $this->city),
            'shipping_rate_uuid' => (string) Str::uuid(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['shipping_rate_uuid']);
});

it('re-selects silently when previewing with a stale option', function () {
    $response = $this->withHeader('X-Cart-Token', $this->cart->session_id)
        ->postJson('/api/v1/checkout/quote', [
            'shipping_address' => cksDestination($this->country, $this->city),
            'shipping_rate_uuid' => (string) Str::uuid(),
        ])
        ->assertOk();

    expect((float) $response->json('data.shipping_fee'))->toBe(400.0);
});
