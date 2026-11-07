<?php

namespace Tests\Feature\Shipping;

use App\Enums\OrderStatus;
use App\Http\Middleware\ApiAuthenticate;
use App\Models\Measurement;
use App\Models\Membership;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\PackagingTemplate;
use App\Models\Shipment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\MeasurementSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    config()->set('auth.guards.api', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    $this->withoutMiddleware(ApiAuthenticate::class);

    $this->user = User::factory()->create();

    $this->tenant = Tenant::create([
        'name' => 'Shipment Tenant',
        'slug' => 'shp-tenant',
        'is_active' => true,
    ]);

    $this->organization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Shipment Vendor',
        'slug' => 'shp-vendor',
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

    $this->order = shpOrder($this->tenant, $this->organization, [
        ['name' => 'Impact Drill', 'sku' => 'SKU-A', 'quantity' => 2],
        ['name' => 'Drill Bit Set', 'sku' => 'SKU-B', 'quantity' => 1],
    ]);

    $this->itemA = $this->order->items->firstWhere('sku', 'SKU-A');
    $this->itemB = $this->order->items->firstWhere('sku', 'SKU-B');
});

function shpUnit(string $measurement, string $code): int
{
    $measurementId = (int) Measurement::query()->where('code', $measurement)->value('id');

    return (int) Unit::query()
        ->where('measurement_id', $measurementId)
        ->where('code', $code)
        ->value('id');
}

/**
 * @param  array<int, array<string, mixed>>  $items
 */
function shpOrder(Tenant $tenant, Organization $organization, array $items): Order
{
    $order = Order::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'order_number' => 'ORD-SHP-'.Str::upper(Str::random(6)),
        'status' => OrderStatus::PENDING,
        'subtotal' => 3000,
        'shipping_fee' => 250,
        'total_amount' => 3250,
        'currency' => 'PKR',
        'shipping_address' => [
            'contact_name' => 'Test Customer',
            'phone' => '+923001234567',
            'address_line_1' => '263 Nishtar Block',
            'city' => 'Lahore',
            'postal_code' => '54570',
        ],
        'placed_at' => now(),
    ]);

    foreach ($items as $index => $item) {
        OrderItem::create([
            'order_id' => $order->id,
            'name' => (string) $item['name'],
            'sku' => (string) $item['sku'],
            'quantity' => $item['quantity'],
            'unit_price' => 1000,
            'total' => 1000 * (float) $item['quantity'],
            'currency' => 'PKR',
        ]);
    }

    return $order->load('items');
}

function shpTemplate(Tenant $tenant): PackagingTemplate
{
    return PackagingTemplate::create([
        'tenant_id' => $tenant->id,
        'name' => 'Medium Box',
        'code' => 'medium-box',
        'packaging_type_unit_id' => shpUnit('packaging', 'box'),
        'length' => 30,
        'width' => 20,
        'height' => 10,
        'dimension_unit_id' => shpUnit('length', 'cm'),
        'max_weight' => 5,
        'weight_unit_id' => shpUnit('mass', 'kg'),
    ]);
}

it('creates a shipment with items and parcels', function () {
    $response = $this->postJson('/api/v1/orders/'.$this->order->uuid.'/shipments', [
        'carrier' => 'manual',
        'service' => 'Overnight',
        'tracking_number' => 'TRK-123',
        'items' => [
            ['order_item_id' => $this->itemA->id, 'quantity' => 2],
        ],
        'parcels' => [
            ['sequence' => 1, 'weight_kg' => 1.5, 'tracking_number' => 'BOX-1'],
            ['sequence' => 2, 'weight_kg' => 2],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('message', 'Shipment created successfully');

    $data = $response->json('data');

    expect($data['status'])->toBe('pending')
        ->and($data['is_open'])->toBeTrue()
        ->and($data['parcel_count'])->toBe(2)
        // 1.5 + 2
        ->and($data['weight_kg'])->toBe(3.5)
        ->and($data['items'])->toHaveCount(1)
        ->and($data['items'][0]['name'])->toBe('Impact Drill')
        ->and($data['items'][0]['quantity'])->toEqual(2.0)
        // The destination is snapshotted from the order.
        ->and($data['ship_to']['city'])->toBe('Lahore');

    $plan = $this->getJson('/api/v1/orders/'.$this->order->uuid.'/shipments')->assertOk();

    expect($plan->json('data.items.0.remaining'))->toEqual(0.0)
        ->and($plan->json('data.items.1.remaining'))->toEqual(1.0)
        ->and($plan->json('data.can_ship'))->toBeTrue();
});

it('rejects shipping more units than remain', function () {
    $this->postJson('/api/v1/orders/'.$this->order->uuid.'/shipments', [
        'items' => [
            ['order_item_id' => $this->itemA->id, 'quantity' => 3],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.quantity']);

    // Dispatch two, then the remaining one is the only sellable quantity.
    $this->postJson('/api/v1/orders/'.$this->order->uuid.'/shipments', [
        'items' => [
            ['order_item_id' => $this->itemA->id, 'quantity' => 2],
        ],
    ])->assertCreated();

    $this->postJson('/api/v1/orders/'.$this->order->uuid.'/shipments', [
        'items' => [
            ['order_item_id' => $this->itemA->id, 'quantity' => 1],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.quantity']);
});

it('rejects an order line that belongs to another order', function () {
    $other = shpOrder($this->tenant, $this->organization, [
        ['name' => 'Other Item', 'sku' => 'SKU-X', 'quantity' => 1],
    ]);

    $this->postJson('/api/v1/orders/'.$this->order->uuid.'/shipments', [
        'items' => [
            ['order_item_id' => $other->items->first()->id, 'quantity' => 1],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.order_item_id']);
});

it('prefills parcel dimensions from the packaging template', function () {
    $template = shpTemplate($this->tenant);

    $response = $this->postJson('/api/v1/orders/'.$this->order->uuid.'/shipments', [
        'items' => [
            ['order_item_id' => $this->itemB->id, 'quantity' => 1],
        ],
        'parcels' => [
            ['packaging_template_id' => $template->id, 'weight_kg' => 1.2],
        ],
    ])
        ->assertCreated();

    $parcel = $response->json('data.parcels.0');

    expect($parcel['packaging_template']['name'])->toBe('Medium Box')
        ->and($parcel['length_cm'])->toEqual(30.0)
        ->and($parcel['width_cm'])->toEqual(20.0)
        ->and($parcel['height_cm'])->toEqual(10.0)
        ->and($parcel['weight_kg'])->toBe(1.2);
});

it('moves the shipment and the order through the delivery lifecycle', function () {
    $shipment = $this->postJson('/api/v1/orders/'.$this->order->uuid.'/shipments', [
        'items' => [
            ['order_item_id' => $this->itemA->id, 'quantity' => 2],
            ['order_item_id' => $this->itemB->id, 'quantity' => 1],
        ],
        'parcels' => [['weight_kg' => 3.5]],
    ])->assertCreated()->json('data');

    $uuid = $shipment['uuid'];

    $this->patchJson('/api/v1/shipments/'.$uuid.'/status', ['status' => 'packing'])
        ->assertOk()
        ->assertJsonPath('data.status', 'packing');

    $this->patchJson('/api/v1/shipments/'.$uuid.'/status', ['status' => 'packed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'packed');

    $packed = $this->getJson('/api/v1/shipments/'.$uuid)->assertOk();
    expect($packed->json('data.packed_at'))->not->toBeNull();

    // Every line is allocated, so advancing the shipment advances the order.
    $this->patchJson('/api/v1/shipments/'.$uuid.'/status', ['status' => 'shipped'])
        ->assertOk()
        ->assertJsonPath('data.status', 'shipped');

    expect($this->order->fresh()->status)->toBe(OrderStatus::SHIPPED);

    $this->patchJson('/api/v1/shipments/'.$uuid.'/status', ['status' => 'delivered'])
        ->assertOk();

    expect($this->order->fresh()->status)->toBe(OrderStatus::DELIVERED);
});

it('refuses to ship without a parcel', function () {
    $uuid = $this->postJson('/api/v1/orders/'.$this->order->uuid.'/shipments', [
        'items' => [
            ['order_item_id' => $this->itemA->id, 'quantity' => 2],
        ],
    ])->assertCreated()->json('data.uuid');

    $this->patchJson('/api/v1/shipments/'.$uuid.'/status', ['status' => 'packing'])->assertOk();
    $this->patchJson('/api/v1/shipments/'.$uuid.'/status', ['status' => 'packed'])->assertOk();

    $this->patchJson('/api/v1/shipments/'.$uuid.'/status', ['status' => 'shipped'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    // Adding a box afterwards unblocks dispatch.
    $this->putJson('/api/v1/shipments/'.$uuid, [
        'parcels' => [['weight_kg' => 2]],
    ])->assertOk();

    $this->patchJson('/api/v1/shipments/'.$uuid.'/status', ['status' => 'shipped'])
        ->assertOk()
        ->assertJsonPath('data.status', 'shipped');
});

it('rejects an illegal status transition', function () {
    $uuid = $this->postJson('/api/v1/orders/'.$this->order->uuid.'/shipments', [
        'items' => [['order_item_id' => $this->itemA->id, 'quantity' => 1]],
    ])->assertCreated()->json('data.uuid');

    // pending → shipped skips packing.
    $this->patchJson('/api/v1/shipments/'.$uuid.'/status', ['status' => 'shipped'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('freezes a dispatched shipment', function () {
    $uuid = $this->postJson('/api/v1/orders/'.$this->order->uuid.'/shipments', [
        'items' => [['order_item_id' => $this->itemA->id, 'quantity' => 1]],
        'parcels' => [['weight_kg' => 1]],
    ])->assertCreated()->json('data.uuid');

    foreach (['packing', 'packed', 'shipped'] as $status) {
        $this->patchJson('/api/v1/shipments/'.$uuid.'/status', ['status' => $status])->assertOk();
    }

    $this->putJson('/api/v1/shipments/'.$uuid, [
        'items' => [['order_item_id' => $this->itemA->id, 'quantity' => 2]],
    ])->assertStatus(422);

    $this->deleteJson('/api/v1/shipments/'.$uuid)->assertStatus(422);

    // Tracking details may still be recorded after dispatch.
    $this->putJson('/api/v1/shipments/'.$uuid, [
        'tracking_number' => 'TRK-999',
    ])
        ->assertOk()
        ->assertJsonPath('data.tracking_number', 'TRK-999');
});

it('deletes an open shipment and releases its allocation', function () {
    $uuid = $this->postJson('/api/v1/orders/'.$this->order->uuid.'/shipments', [
        'items' => [['order_item_id' => $this->itemA->id, 'quantity' => 2]],
    ])->assertCreated()->json('data.uuid');

    $this->deleteJson('/api/v1/shipments/'.$uuid)->assertOk();

    $plan = $this->getJson('/api/v1/orders/'.$this->order->uuid.'/shipments')->assertOk();

    expect($plan->json('data.shipments'))->toHaveCount(0)
        ->and($plan->json('data.items.0.remaining'))->toEqual(2.0);
});

it('forbids users outside the order tenant', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider, 'api')
        ->getJson('/api/v1/orders/'.$this->order->uuid.'/shipments')
        ->assertForbidden();

    $this->actingAs($outsider, 'api')
        ->postJson('/api/v1/orders/'.$this->order->uuid.'/shipments', [
            'items' => [['order_item_id' => $this->itemA->id, 'quantity' => 1]],
        ])
        ->assertForbidden();
});

it('recomputes the plan after a shipment is cancelled', function () {
    $uuid = $this->postJson('/api/v1/orders/'.$this->order->uuid.'/shipments', [
        'items' => [['order_item_id' => $this->itemA->id, 'quantity' => 2]],
    ])->assertCreated()->json('data.uuid');

    $this->patchJson('/api/v1/shipments/'.$uuid.'/status', ['status' => 'cancelled'])->assertOk();

    $plan = $this->getJson('/api/v1/orders/'.$this->order->uuid.'/shipments')->assertOk();

    // Cancelled shipments no longer reserve stock.
    expect($plan->json('data.items.0.remaining'))->toEqual(2.0)
        ->and($plan->json('data.can_ship'))->toBeTrue();

    // The record itself is kept for the audit trail.
    expect(Shipment::query()->where('uuid', $uuid)->exists())->toBeTrue();
});
