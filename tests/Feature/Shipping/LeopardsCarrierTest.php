<?php

namespace Tests\Feature\Shipping;

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Measurement;
use App\Models\Membership;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductShipping;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Shipping\ShippingQuoteService;
use App\Support\Shipping\Carriers\CarrierQuoteRequest;
use App\Support\Shipping\Carriers\LeopardsBookingProvider;
use App\Support\Shipping\Carriers\LeopardsRateProvider;
use App\Support\Shipping\ShippingQuote;
use Database\Seeders\MeasurementSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The Leopards integration: live rates, booking (TTN) and label, plus the
 * carrier picker data the management UI needs. Every outbound call is faked.
 */
beforeEach(function () {
    config([
        'shipping.enabled' => true,
        'shipping.rates.PKR' => ['flat_rate' => 999, 'free_over' => null],
        'shipping.rates.USD' => ['flat_rate' => 10, 'free_over' => null],
        'shipping.default' => ['flat_rate' => 0, 'free_over' => null],
        'shipping.volumetric_divisor' => 5000,
        'shipping.carriers' => [
            'leopards' => [
                'driver' => LeopardsRateProvider::class,
                'booking_driver' => LeopardsBookingProvider::class,
                'enabled' => true,
                'api_key' => 'test-key',
                'api_password' => 'test-pass',
                'api_base' => 'https://api.leopardscourier.com/api',
                'origin_city' => 'Lahore',
                'shipment_type' => 'overnight',
                'estimated_days_min' => 2,
                'estimated_days_max' => 4,
                'timeout' => 5,
                'retries' => 0,
                'rate_path' => 'getShipmentCharges/format/json',
                'book_path' => 'bookPacket/format/json',
                'cancel_path' => 'cancelBookedPacket/format/json',
            ],
        ],
    ]);

    config()->set('auth.guards.api', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    $this->withoutMiddleware(ApiAuthenticate::class);

    $this->user = User::factory()->create();

    $this->tenant = Tenant::create([
        'name' => 'Leopards Tenant',
        'slug' => 'leo-tenant',
        'is_active' => true,
    ]);

    $this->organization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Leopards Vendor',
        'slug' => 'leo-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    Membership::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'user_id' => $this->user->id,
        'role' => 'owner',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $this->seed(MeasurementSeeder::class);

    $this->actingAs($this->user, 'api');

    $this->order = leoOrder($this->tenant, $this->organization);
});

function leoUnit(string $measurement, string $code): int
{
    $measurementId = (int) Measurement::query()->where('code', $measurement)->value('id');

    return (int) Unit::query()
        ->where('measurement_id', $measurementId)
        ->where('code', $code)
        ->value('id');
}

function leoOrder(Tenant $tenant, Organization $organization): Order
{
    $order = Order::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'order_number' => 'ORD-LEO-'.Str::upper(Str::random(6)),
        'status' => 'pending',
        'subtotal' => 3000,
        'shipping_fee' => 250,
        'total_amount' => 3250,
        'currency' => 'PKR',
        'shipping_address' => [
            'contact_name' => 'Test Customer',
            'phone' => '+923001234567',
            'email' => 'buyer@example.test',
            'address_line_1' => '263 Nishtar Block',
            'city' => 'Karachi',
            'postal_code' => '74200',
        ],
        'placed_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'name' => 'Impact Drill',
        'sku' => 'SKU-A',
        'quantity' => 1,
        'unit_price' => 3000,
        'total' => 3000,
        'currency' => 'PKR',
    ]);

    return $order->load('items');
}

/**
 * @return array<string, mixed>
 */
function leoBookingResponse(): array
{
    return [
        'status' => 1,
        'packet_list' => [[
            'booked_packet_id' => 987,
            'booked_packet_no' => 'LEO-123',
            'track_number' => 'LEO-123',
        ]],
        'slip_link' => 'https://leopards.test/label/LEO-123.pdf',
    ];
}

function leoRateProvider(): LeopardsRateProvider
{
    return new LeopardsRateProvider((array) config('shipping.carriers.leopards'));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function leoShipment(Order $order, string $carrier = 'leopards', array $overrides = []): array
{
    return test()->postJson('/api/v1/orders/'.$order->uuid.'/shipments', array_merge([
        'carrier' => $carrier,
        'items' => [
            ['order_item_id' => $order->items->first()->id, 'quantity' => 1],
        ],
        'parcels' => [['packaging_template_id' => null, 'weight_kg' => 2]],
    ], $overrides))->assertCreated()->json('data');
}

it('quotes a live Leopards rate', function () {
    Http::fake([
        'api.leopardscourier.com/*' => Http::response([
            'status' => 1,
            'charges' => ['charges' => 350],
        ], 200),
    ]);

    $options = leoRateProvider()->quote(new CarrierQuoteRequest(
        weightKg: 2,
        lengthCm: 30,
        widthCm: 20,
        heightCm: 10,
        currency: 'PKR',
        subtotal: 3000,
        itemCount: 1,
        city: 'Karachi',
    ));

    expect($options)->toHaveCount(1)
        ->and($options[0]->carrier)->toBe('leopards')
        ->and($options[0]->amount)->toBe(350.0)
        ->and($options[0]->estimatedDaysMin)->toBe(2)
        ->and($options[0]->estimatedDaysMax)->toBe(4);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'getShipmentCharges')
        && $request['api_key'] === 'test-key'
        && $request['api_password'] === 'test-pass'
        && $request['destination_city'] === 'Karachi'
        && (float) $request['weight'] === 2.0);
});

it('ignores Leopards for non-PKR baskets and failed lookups', function () {
    Http::fake([
        'api.leopardscourier.com/*' => Http::response(['status' => 0, 'error' => 'Invalid city'], 200),
    ]);

    $provider = leoRateProvider();

    expect($provider->quote(new CarrierQuoteRequest(
        weightKg: 2,
        lengthCm: null,
        widthCm: null,
        heightCm: null,
        currency: 'USD',
        subtotal: 100,
        itemCount: 1,
        city: 'Karachi',
    )))->toBe([]);

    // A rejected lookup is logged and skipped, never surfaced to checkout.
    expect($provider->quote(new CarrierQuoteRequest(
        weightKg: 2,
        lengthCm: null,
        widthCm: null,
        heightCm: null,
        currency: 'PKR',
        subtotal: 100,
        itemCount: 1,
        city: 'Karachi',
    )))->toBe([]);
});

it('offers the carrier rate in the checkout quote', function () {
    Http::fake([
        'api.leopardscourier.com/*' => Http::response(['status' => 1, 'charges' => 350], 200),
    ]);

    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'name' => 'Shippable Widget',
        'slug' => 'leo-widget-'.Str::lower(Str::random(6)),
        'status' => 'active',
        'is_active' => true,
        'metadata' => ['price' => 1000, 'currency' => 'PKR'],
    ]);

    ProductShipping::create([
        'product_id' => $product->id,
        'gross_weight' => 2,
        'weight_unit_id' => leoUnit('mass', 'kg'),
        'length' => 30,
        'width' => 20,
        'height' => 10,
        'dimension_unit_id' => leoUnit('length', 'cm'),
        'is_stackable' => true,
    ]);

    $cart = Cart::create([
        'session_id' => 'leo-cart-'.Str::lower(Str::random(8)),
        'currency' => 'PKR',
        'status' => 'active',
    ]);

    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 1000,
        'currency' => 'PKR',
    ]);

    $quote = app(ShippingQuoteService::class)->forCart(
        $cart,
        ['country_id' => 1, 'city' => 'Karachi'],
        'PKR',
        1000.0,
        null,
        $this->tenant->id,
    );

    expect($quote->source)->toBe(ShippingQuote::SOURCE_CARRIER)
        ->and($quote->fee())->toBe(350.0)
        ->and($quote->selected?->carrier)->toBe('leopards');
});

it('books a shipment and stores the TTN and label', function () {
    Http::fake([
        'api.leopardscourier.com/*' => Http::response(leoBookingResponse(), 200),
    ]);

    $shipment = leoShipment($this->order);

    paymentRecord($this->order, [
        'payment_method' => 'cod',
        'status' => 'pending',
    ]);

    $response = $this->postJson('/api/v1/shipments/'.$shipment['uuid'].'/book')
        ->assertOk()
        ->assertJsonPath('data.tracking_number', 'LEO-123')
        ->assertJsonPath('data.carrier_booking_id', '987')
        ->assertJsonPath('data.label_url', 'https://leopards.test/label/LEO-123.pdf');

    expect($response->json('data.booked_at'))->not->toBeNull();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'bookPacket')
        && (float) $request['booked_packet_weight'] === 2.0
        && (int) $request['booked_packet_no_piece'] === 1
        && (float) $request['booked_packet_collect_amount'] === 3250.0
        && $request['destination_city'] === 'Karachi'
        && $request['consignment_phone'] === '+923001234567');
});

it('is idempotent when a shipment is already booked', function () {
    Http::fake([
        'api.leopardscourier.com/*' => Http::response(leoBookingResponse(), 200),
    ]);

    $shipment = leoShipment($this->order);

    $this->postJson('/api/v1/shipments/'.$shipment['uuid'].'/book')->assertOk();
    $this->postJson('/api/v1/shipments/'.$shipment['uuid'].'/book')
        ->assertOk()
        ->assertJsonPath('data.tracking_number', 'LEO-123');

    // Only the first call reached the carrier.
    Http::assertSentCount(1);
});

it('requires a parcel and a weight before booking', function () {
    Http::fake();

    $withoutParcel = $this->postJson('/api/v1/orders/'.$this->order->uuid.'/shipments', [
        'carrier' => 'leopards',
        'items' => [['order_item_id' => $this->order->items->first()->id, 'quantity' => 1]],
    ])->assertCreated()->json('data');

    $this->postJson('/api/v1/shipments/'.$withoutParcel['uuid'].'/book')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['shipment']);

    $secondOrder = leoOrder($this->tenant, $this->organization);

    $withoutWeight = leoShipment($secondOrder, overrides: [
        'parcels' => [['packaging_template_id' => null]],
    ]);

    $this->postJson('/api/v1/shipments/'.$withoutWeight['uuid'].'/book')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['shipment']);

    Http::assertNothingSent();
});

it('rejects booking with a carrier that is not configured', function () {
    Http::fake();

    $shipment = leoShipment($this->order, carrier: 'manual');

    $this->postJson('/api/v1/shipments/'.$shipment['uuid'].'/book')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['carrier']);

    Http::assertNothingSent();
});

it('reports a carrier rejection without losing the shipment', function () {
    Http::fake([
        'api.leopardscourier.com/*' => Http::response([
            'status' => 0,
            'error' => 'Insufficient balance',
        ], 200),
    ]);

    $shipment = leoShipment($this->order);

    $this->postJson('/api/v1/shipments/'.$shipment['uuid'].'/book')
        ->assertStatus(502)
        ->assertJsonPath('message', 'Insufficient balance');

    // The local record is untouched, so the operator can retry.
    $this->getJson('/api/v1/shipments/'.$shipment['uuid'])
        ->assertOk()
        ->assertJsonPath('data.tracking_number', null);
});

it('cancels a carrier booking and clears the TTN', function () {
    Http::fake([
        'api.leopardscourier.com/api/cancelBookedPacket/*' => Http::response(['status' => 1], 200),
        'api.leopardscourier.com/*' => Http::response(leoBookingResponse(), 200),
    ]);

    $shipment = leoShipment($this->order);

    $this->postJson('/api/v1/shipments/'.$shipment['uuid'].'/book')->assertOk();

    $this->postJson('/api/v1/shipments/'.$shipment['uuid'].'/cancel-booking')
        ->assertOk()
        ->assertJsonPath('data.tracking_number', null)
        ->assertJsonPath('data.carrier_booking_id', null)
        ->assertJsonPath('data.label_url', null);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'cancelBookedPacket')
        && $request['booked_packet_no'] === 'LEO-123');
});

it('refuses to cancel a booking that does not exist', function () {
    Http::fake();

    $shipment = leoShipment($this->order);

    $this->postJson('/api/v1/shipments/'.$shipment['uuid'].'/cancel-booking')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['shipment']);

    Http::assertNothingSent();
});

it('lists the configured carriers', function () {
    $response = $this->getJson('/api/v1/shipping-carriers')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.code'))->toBe('leopards')
        ->and($response->json('data.0.rates'))->toBeTrue()
        ->and($response->json('data.0.booking'))->toBeTrue();
});

it('forbids outsiders from booking a shipment', function () {
    Http::fake();

    $shipment = leoShipment($this->order);
    $outsider = User::factory()->create();

    $this->actingAs($outsider, 'api')
        ->postJson('/api/v1/shipments/'.$shipment['uuid'].'/book')
        ->assertForbidden();

    Http::assertNothingSent();
});
