<?php

namespace Tests\Feature\Shipping;

use App\Enums\ShipmentStatus;
use App\Http\Middleware\ApiAuthenticate;
use App\Models\Definition;
use App\Models\Measurement;
use App\Models\Membership;
use App\Models\Option;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\PackagingTemplate;
use App\Models\Product;
use App\Models\ProductShipping;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
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
        'name' => 'Packing API Tenant',
        'slug' => 'pack-api',
        'is_active' => true,
    ]);

    $this->organization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Packing API Vendor',
        'slug' => 'pack-api-vendor',
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
});

function spUnit(string $measurement, string $code): int
{
    $measurementId = (int) Measurement::query()->where('code', $measurement)->value('id');

    return (int) Unit::query()
        ->where('measurement_id', $measurementId)
        ->where('code', $code)
        ->value('id');
}

function spHazardOption(Tenant $tenant, string $code = '3'): int
{
    $definition = Definition::query()->firstOrCreate(
        ['tenant_id' => $tenant->id, 'code' => 'pkg_hazard_class'],
        ['name' => 'Hazard Class', 'value_type' => 'select'],
    );

    return (int) Option::query()->firstOrCreate(
        ['definition_id' => $definition->id, 'code' => $code],
        ['name' => 'Class '.$code],
    )->id;
}

function spTemplate(Tenant $tenant): PackagingTemplate
{
    return PackagingTemplate::create([
        'tenant_id' => $tenant->id,
        'name' => 'Medium Box',
        'code' => 'medium-box',
        'packaging_type_unit_id' => spUnit('packaging', 'box'),
        'length' => 30,
        'width' => 20,
        'height' => 10,
        'dimension_unit_id' => spUnit('length', 'cm'),
        'max_weight' => 10,
        'weight_unit_id' => spUnit('mass', 'kg'),
        'is_active' => true,
    ]);
}

/**
 * @param  array<int, array<string, mixed>>  $lines
 */
function spOrder(Tenant $tenant, Organization $organization, array $lines): Order
{
    $order = Order::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'order_number' => 'ORD-PACK-'.Str::upper(Str::random(6)),
        'status' => 'pending',
        'subtotal' => 1000,
        'shipping_fee' => 0,
        'total_amount' => 1000,
        'currency' => 'PKR',
        'shipping_address' => ['city' => 'Lahore'],
        'placed_at' => now(),
    ]);

    foreach ($lines as $index => $line) {
        $product = Product::create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'name' => (string) ($line['name'] ?? 'Packed Item '.$index),
            'slug' => 'packed-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'is_active' => true,
        ]);

        ProductShipping::create([
            'product_id' => $product->id,
            'gross_weight' => 1,
            'weight_unit_id' => spUnit('mass', 'kg'),
            'length' => 10,
            'width' => 10,
            'height' => 10,
            'dimension_unit_id' => spUnit('length', 'cm'),
            'ships_alone' => false,
            'is_fragile' => false,
            'is_stackable' => true,
            'is_hazmat' => (bool) ($line['hazmat'] ?? false),
            'hazard_class_option_id' => $line['hazard_class'] ?? null,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'quantity' => $line['quantity'] ?? 1,
            'unit_price' => 100,
            'total' => 100 * (float) ($line['quantity'] ?? 1),
            'currency' => 'PKR',
        ]);
    }

    return $order->load('items');
}

it('records box contents and derives the hazmat flag', function () {
    $order = spOrder($this->tenant, $this->organization, [
        ['name' => 'Solvent', 'quantity' => 2, 'hazmat' => true, 'hazard_class' => spHazardOption($this->tenant, '3')],
        ['name' => 'Gloves', 'quantity' => 1],
    ]);

    $solvent = $order->items->firstWhere('name', 'Solvent');
    $gloves = $order->items->firstWhere('name', 'Gloves');

    $response = $this->postJson('/api/v1/orders/'.$order->uuid.'/shipments', [
        'items' => [
            ['order_item_id' => $solvent->id, 'quantity' => 2],
            ['order_item_id' => $gloves->id, 'quantity' => 1],
        ],
        'parcels' => [
            [
                'sequence' => 1,
                'weight_kg' => 2,
                'items' => [['order_item_id' => $solvent->id, 'quantity' => 2]],
            ],
            [
                'sequence' => 2,
                'weight_kg' => 1,
                'items' => [['order_item_id' => $gloves->id, 'quantity' => 1]],
            ],
        ],
    ])->assertCreated();

    $data = $response->json('data');

    expect($data['contains_hazmat'])->toBeTrue()
        ->and($data['unpacked'])->toBeFalse()
        ->and($data['parcels'][0]['contains_hazmat'])->toBeTrue()
        ->and($data['parcels'][1]['contains_hazmat'])->toBeFalse()
        ->and($data['parcels'][0]['items'][0]['name'])->toBe('Solvent')
        ->and($data['parcels'][0]['items'][0]['quantity'])->toEqual(2.0);

    $packed = collect($data['packed'])->firstWhere('order_item_id', $solvent->id);

    expect($packed['quantity'])->toEqual(2.0);
});

it('rejects boxes that carry more than the shipment', function () {
    $order = spOrder($this->tenant, $this->organization, [
        ['name' => 'Drill', 'quantity' => 2],
    ]);

    $item = $order->items->first();

    $this->postJson('/api/v1/orders/'.$order->uuid.'/shipments', [
        'items' => [['order_item_id' => $item->id, 'quantity' => 2]],
        'parcels' => [
            ['sequence' => 1, 'weight_kg' => 1, 'items' => [['order_item_id' => $item->id, 'quantity' => 2]]],
            ['sequence' => 2, 'weight_kg' => 1, 'items' => [['order_item_id' => $item->id, 'quantity' => 1]]],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['parcels.1.items.0.quantity']);
});

it('rejects a box line that is not part of the shipment', function () {
    $order = spOrder($this->tenant, $this->organization, [
        ['name' => 'Drill', 'quantity' => 1],
    ]);

    $other = spOrder($this->tenant, $this->organization, [
        ['name' => 'Saw', 'quantity' => 1],
    ]);

    $this->postJson('/api/v1/orders/'.$order->uuid.'/shipments', [
        'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => 1]],
        'parcels' => [
            ['weight_kg' => 1, 'items' => [['order_item_id' => $other->items->first()->id, 'quantity' => 1]]],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['parcels.0.items.0.order_item_id']);
});

it('keeps box contents when only the weight is corrected', function () {
    $order = spOrder($this->tenant, $this->organization, [
        ['name' => 'Drill', 'quantity' => 2],
    ]);

    $item = $order->items->first();

    $uuid = $this->postJson('/api/v1/orders/'.$order->uuid.'/shipments', [
        'items' => [['order_item_id' => $item->id, 'quantity' => 2]],
        'parcels' => [
            ['sequence' => 1, 'weight_kg' => 1.5, 'items' => [['order_item_id' => $item->id, 'quantity' => 2]]],
        ],
    ])->assertCreated()->json('data.uuid');

    $updated = $this->putJson('/api/v1/shipments/'.$uuid, [
        'parcels' => [['sequence' => 1, 'weight_kg' => 4.2]],
    ])->assertOk()->json('data');

    expect($updated['parcels'][0]['weight_kg'])->toBe(4.2)
        ->and($updated['parcels'][0]['items'])->toHaveCount(1)
        ->and($updated['parcels'][0]['items'][0]['quantity'])->toEqual(2.0)
        ->and($updated['unpacked'])->toBeFalse();
});

it('previews and applies the packing plan idempotently', function () {
    spTemplate($this->tenant);

    $order = spOrder($this->tenant, $this->organization, [
        ['name' => 'Drill', 'quantity' => 2],
    ]);

    $item = $order->items->first();

    $uuid = $this->postJson('/api/v1/orders/'.$order->uuid.'/shipments', [
        'items' => [['order_item_id' => $item->id, 'quantity' => 2]],
    ])->assertCreated()->json('data.uuid');

    $preview = $this->getJson('/api/v1/shipments/'.$uuid.'/packing-plan')
        ->assertOk()
        ->json('data');

    expect($preview['boxes'])->toHaveCount(1)
        ->and($preview['boxes'][0]['packaging_template'])->not->toBeNull();

    // Previewing must not write anything.
    $this->getJson('/api/v1/shipments/'.$uuid)->assertOk()->assertJsonPath('data.parcel_count', 0);

    $applied = $this->postJson('/api/v1/shipments/'.$uuid.'/apply-plan')
        ->assertOk()
        ->json('data');

    expect($applied['shipment']['parcel_count'])->toBe(1)
        ->and($applied['shipment']['unpacked'])->toBeFalse()
        ->and($applied['shipment']['parcels'][0]['items'][0]['quantity'])->toEqual(2.0);

    $again = $this->postJson('/api/v1/shipments/'.$uuid.'/apply-plan')
        ->assertOk()
        ->json('data');

    expect($again['shipment']['parcel_count'])->toBe(1);
});

it('creates and auto-packs a shipment in one request', function () {
    spTemplate($this->tenant);

    $order = spOrder($this->tenant, $this->organization, [
        ['name' => 'Drill', 'quantity' => 1],
    ]);

    $data = $this->postJson('/api/v1/orders/'.$order->uuid.'/shipments', [
        'auto_pack' => true,
        'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => 1]],
    ])->assertCreated()->json('data');

    expect($data['parcel_count'])->toBe(1)
        ->and($data['unpacked'])->toBeFalse();
});

it('refuses to re-pack a dispatched shipment', function () {
    $order = spOrder($this->tenant, $this->organization, [
        ['name' => 'Drill', 'quantity' => 1],
    ]);

    $uuid = $this->postJson('/api/v1/orders/'.$order->uuid.'/shipments', [
        'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => 1]],
        'parcels' => [['weight_kg' => 1]],
    ])->assertCreated()->json('data.uuid');

    foreach ([ShipmentStatus::PACKING, ShipmentStatus::PACKED, ShipmentStatus::SHIPPED] as $status) {
        $this->patchJson('/api/v1/shipments/'.$uuid.'/status', ['status' => $status])->assertOk();
    }

    $this->postJson('/api/v1/shipments/'.$uuid.'/apply-plan')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['shipment']);
});

it('snapshots the warehouse a shipment leaves from', function () {
    $warehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Karachi DC',
        'city' => 'Karachi',
        'state' => 'Sindh',
        'address_line_1' => 'Plot 12, Port Area',
        'is_active' => true,
    ]);

    $order = spOrder($this->tenant, $this->organization, [
        ['name' => 'Drill', 'quantity' => 1],
    ]);

    $data = $this->postJson('/api/v1/orders/'.$order->uuid.'/shipments', [
        'warehouse_id' => $warehouse->id,
        'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => 1]],
    ])->assertCreated()->json('data');

    expect($data['warehouse']['name'])->toBe('Karachi DC')
        ->and($data['ship_from']['city'])->toBe('Karachi');
});

it('rejects a warehouse that belongs to another tenant', function () {
    $otherTenant = Tenant::create([
        'name' => 'Other Tenant',
        'slug' => 'other-pack',
        'is_active' => true,
    ]);

    $warehouse = Warehouse::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Their DC',
        'city' => 'Islamabad',
        'is_active' => true,
    ]);

    $order = spOrder($this->tenant, $this->organization, [
        ['name' => 'Drill', 'quantity' => 1],
    ]);

    $this->postJson('/api/v1/orders/'.$order->uuid.'/shipments', [
        'warehouse_id' => $warehouse->id,
        'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => 1]],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['warehouse_id']);
});

it('marks a shipment unpacked while lines are not in a box', function () {
    $order = spOrder($this->tenant, $this->organization, [
        ['name' => 'Drill', 'quantity' => 2],
    ]);

    $item = $order->items->first();

    $uuid = $this->postJson('/api/v1/orders/'.$order->uuid.'/shipments', [
        'items' => [['order_item_id' => $item->id, 'quantity' => 2]],
        'parcels' => [
            ['weight_kg' => 1, 'items' => [['order_item_id' => $item->id, 'quantity' => 1]]],
        ],
    ])->assertCreated()->json('data.uuid');

    $this->getJson('/api/v1/shipments/'.$uuid)
        ->assertOk()
        ->assertJsonPath('data.unpacked', true)
        ->assertJsonPath('data.parcels.0.contains_hazmat', false);
});
