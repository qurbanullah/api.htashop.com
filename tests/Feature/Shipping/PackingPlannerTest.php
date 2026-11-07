<?php

namespace Tests\Feature\Shipping;

use App\Enums\ShipmentStatus;
use App\Models\Definition;
use App\Models\Measurement;
use App\Models\Option;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\PackagingTemplate;
use App\Models\Product;
use App\Models\ProductShipping;
use App\Models\Shipment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Services\Shipping\PackingPlanner;
use Database\Seeders\MeasurementSeeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(MeasurementSeeder::class);
});

function ppUnit(string $measurement, string $code): int
{
    $measurementId = (int) Measurement::query()->where('code', $measurement)->value('id');

    return (int) Unit::query()
        ->where('measurement_id', $measurementId)
        ->where('code', $code)
        ->value('id');
}

function ppTenant(): Tenant
{
    return Tenant::create([
        'name' => 'Packing Tenant',
        'slug' => 'pack-'.Str::lower(Str::random(8)),
        'is_active' => true,
    ]);
}

function ppOrganization(Tenant $tenant): Organization
{
    return Organization::create([
        'tenant_id' => $tenant->id,
        'name' => 'Packing Vendor',
        'slug' => 'pack-org-'.Str::lower(Str::random(8)),
        'type' => 'vendor',
        'is_active' => true,
    ]);
}

function ppHazardOption(Tenant $tenant, string $code = '3'): int
{
    $definition = Definition::query()->firstOrCreate(
        ['tenant_id' => $tenant->id, 'code' => 'pkg_hazard_class'],
        ['name' => 'Hazard Class', 'value_type' => 'select'],
    );

    $option = Option::query()->firstOrCreate(
        ['definition_id' => $definition->id, 'code' => $code],
        ['name' => 'Class '.$code],
    );

    return (int) $option->id;
}

function ppTemplate(
    Tenant $tenant,
    string $name,
    float $length,
    float $width,
    float $height,
    ?float $maxWeight = 50,
): PackagingTemplate {
    return PackagingTemplate::create([
        'tenant_id' => $tenant->id,
        'name' => $name,
        'code' => Str::slug($name).'-'.Str::lower(Str::random(4)),
        'packaging_type_unit_id' => ppUnit('packaging', 'box'),
        'length' => $length,
        'width' => $width,
        'height' => $height,
        'dimension_unit_id' => ppUnit('length', 'cm'),
        'max_weight' => $maxWeight,
        'weight_unit_id' => ppUnit('mass', 'kg'),
        'is_active' => true,
    ]);
}

/**
 * Build a shipment whose lines point at products with real shipping data.
 *
 * @param  array<int, array<string, mixed>>  $lines
 */
function ppShipment(Tenant $tenant, Organization $organization, array $lines): Shipment
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

    $shipment = Shipment::create([
        'order_id' => $order->id,
        'tenant_id' => $tenant->id,
        'status' => ShipmentStatus::PENDING,
        'ship_to' => ['city' => 'Lahore'],
    ]);

    foreach ($lines as $index => $line) {
        $product = Product::create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'name' => (string) ($line['name'] ?? 'Packing Item '.$index),
            'slug' => 'pack-item-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'is_active' => true,
        ]);

        ProductShipping::create([
            'product_id' => $product->id,
            'gross_weight' => $line['weight'] ?? null,
            'weight_unit_id' => ppUnit('mass', 'kg'),
            'length' => $line['dims'][0] ?? null,
            'width' => $line['dims'][1] ?? null,
            'height' => $line['dims'][2] ?? null,
            'dimension_unit_id' => ppUnit('length', 'cm'),
            'ships_alone' => (bool) ($line['alone'] ?? false),
            'is_fragile' => false,
            'is_stackable' => (bool) ($line['stackable'] ?? true),
            'is_hazmat' => (bool) ($line['hazmat'] ?? false),
            'hazard_class_option_id' => $line['hazard_class'] ?? null,
        ]);

        $quantity = (float) ($line['quantity'] ?? 1);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'quantity' => $quantity,
            'unit_price' => 100,
            'total' => 100 * $quantity,
            'currency' => 'PKR',
        ]);

        $shipment->items()->create([
            'order_item_id' => $item->id,
            'quantity' => $quantity,
        ]);
    }

    return $shipment;
}

function ppPlan(Shipment $shipment)
{
    return app(PackingPlanner::class)->plan($shipment);
}

it('packs units into the smallest template that fits', function () {
    $tenant = ppTenant();
    $organization = ppOrganization($tenant);

    ppTemplate($tenant, 'Small', 20, 15, 10);
    ppTemplate($tenant, 'Large', 40, 30, 30);

    $shipment = ppShipment($tenant, $organization, [
        ['quantity' => 2, 'weight' => 1, 'dims' => [10, 10, 10]],
    ]);

    $plan = ppPlan($shipment);

    expect($plan->boxes)->toHaveCount(1)
        ->and($plan->boxes[0]->templateName)->toBe('Small')
        ->and($plan->boxes[0]->items)->toBe([[
            'order_item_id' => (int) $shipment->items->first()->order_item_id,
            'quantity' => 2.0,
        ]])
        ->and($plan->boxes[0]->lengthCm)->toEqual(20.0);
});

it('opens another box when the volume no longer fits', function () {
    $tenant = ppTenant();
    $organization = ppOrganization($tenant);

    ppTemplate($tenant, 'Small', 20, 15, 10);

    $shipment = ppShipment($tenant, $organization, [
        ['quantity' => 4, 'weight' => 1, 'dims' => [10, 10, 10]],
    ]);

    $plan = ppPlan($shipment);

    expect($plan->boxes)->toHaveCount(2)
        ->and($plan->boxes[0]->items[0]['quantity'])->toEqual(3.0)
        ->and($plan->boxes[1]->items[0]['quantity'])->toEqual(1.0);
});

it('gives a ships-alone item a box of its own', function () {
    $tenant = ppTenant();
    $organization = ppOrganization($tenant);

    ppTemplate($tenant, 'Small', 20, 15, 10);

    $shipment = ppShipment($tenant, $organization, [
        ['quantity' => 1, 'weight' => 1, 'dims' => [10, 10, 10], 'alone' => true],
        ['quantity' => 1, 'weight' => 1, 'dims' => [10, 10, 10]],
    ]);

    expect(ppPlan($shipment)->boxes)->toHaveCount(2);
});

it('never mixes hazard classes and keeps hazardous goods apart', function () {
    $tenant = ppTenant();
    $organization = ppOrganization($tenant);

    ppTemplate($tenant, 'Large', 60, 40, 40);

    $shipment = ppShipment($tenant, $organization, [
        ['quantity' => 1, 'weight' => 1, 'dims' => [5, 5, 5], 'hazmat' => true, 'hazard_class' => ppHazardOption($tenant, '3')],
        ['quantity' => 1, 'weight' => 1, 'dims' => [5, 5, 5], 'hazmat' => true, 'hazard_class' => ppHazardOption($tenant, '3')],
        ['quantity' => 1, 'weight' => 1, 'dims' => [5, 5, 5], 'hazmat' => true, 'hazard_class' => ppHazardOption($tenant, '8')],
        ['quantity' => 1, 'weight' => 1, 'dims' => [5, 5, 5]],
    ]);

    $plan = ppPlan($shipment);

    $classes = array_map(
        fn ($box) => $box->hazardClassOptionId,
        array_filter($plan->boxes, fn ($box) => $box->containsHazmat),
    );

    expect($plan->boxes)->toHaveCount(3)
        ->and(array_values($classes))->toBe([ppHazardOption($tenant, '3'), ppHazardOption($tenant, '8')])
        ->and(array_filter($plan->boxes, fn ($box) => ! $box->containsHazmat))->toHaveCount(1);
});

it('boxes an unclassified hazardous item on its own', function () {
    $tenant = ppTenant();
    $organization = ppOrganization($tenant);

    ppTemplate($tenant, 'Large', 60, 40, 40);

    $shipment = ppShipment($tenant, $organization, [
        ['quantity' => 1, 'weight' => 1, 'dims' => [5, 5, 5], 'hazmat' => true],
        ['quantity' => 1, 'weight' => 1, 'dims' => [5, 5, 5]],
    ]);

    $plan = ppPlan($shipment);

    $hazmatBox = collect($plan->boxes)->firstWhere('containsHazmat', true);

    expect($plan->boxes)->toHaveCount(2)
        ->and($hazmatBox)->not->toBeNull()
        ->and($hazmatBox->hazardClassOptionId)->toBe(-1);
});

it('warns when a line has no weight or dimensions', function () {
    $tenant = ppTenant();
    $organization = ppOrganization($tenant);

    ppTemplate($tenant, 'Small', 20, 15, 10);

    $shipment = ppShipment($tenant, $organization, [
        ['quantity' => 1],
    ]);

    $plan = ppPlan($shipment);

    expect($plan->warnings)->toHaveCount(2);
});

it('falls back to a custom box when no template is large enough', function () {
    $tenant = ppTenant();
    $organization = ppOrganization($tenant);

    ppTemplate($tenant, 'Small', 20, 15, 10);

    $shipment = ppShipment($tenant, $organization, [
        ['quantity' => 1, 'weight' => 20, 'dims' => [100, 100, 100]],
    ]);

    $plan = ppPlan($shipment);

    expect($plan->boxes)->toHaveCount(1)
        ->and($plan->boxes[0]->templateId)->toBeNull()
        ->and($plan->boxes[0]->lengthCm)->toEqual(100.0)
        ->and($plan->warnings)->not->toBeEmpty();
});

it('refuses to expand an absurd number of units', function () {
    $tenant = ppTenant();
    $organization = ppOrganization($tenant);

    ppTemplate($tenant, 'Small', 20, 15, 10);

    $shipment = ppShipment($tenant, $organization, [
        ['quantity' => 301, 'weight' => 1, 'dims' => [10, 10, 10]],
    ]);

    expect(fn () => ppPlan($shipment))->toThrow(ValidationException::class);
});
