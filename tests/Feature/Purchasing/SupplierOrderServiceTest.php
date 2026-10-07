<?php

use App\Enums\OrderStatus;
use App\Enums\Sourcing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\Purchasing\SupplierOrderService;
use Illuminate\Support\Str;

function poTenant(): Tenant
{
    return Tenant::query()->create([
        'name' => 'HTAShop',
        'slug' => 'po-'.Str::lower(Str::random(8)),
        'is_active' => true,
    ]);
}

function poProduct(Tenant $tenant, array $attributes = []): Product
{
    $organization = Organization::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'HTAShop Store',
        'slug' => 'po-org-'.Str::lower(Str::random(8)),
    ]);

    return Product::query()->create(array_merge([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'name' => 'BLDC Motor',
        'slug' => 'po-prod-'.Str::lower(Str::random(8)),
        'status' => 'active',
        'is_active' => true,
        'sku' => 'PO-SKU',
        'metadata' => ['price' => 1000, 'currency' => 'PKR'],
    ], $attributes));
}

function poOrder(Tenant $tenant, string $status): Order
{
    return Order::query()->create([
        'tenant_id' => $tenant->id,
        'status' => $status,
        'total_amount' => 1000,
        'currency' => 'PKR',
    ]);
}

function poItem(Order $order, Product $product, int $quantity): OrderItem
{
    return OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'name' => $product->name,
        'sku' => $product->sku,
        'quantity' => $quantity,
        'unit_price' => 1000,
        'total' => 1000 * $quantity,
        'currency' => 'PKR',
    ]);
}

it('rolls open on-demand order items up by SKU', function () {
    $tenant = poTenant();
    $motor = poProduct($tenant, [
        'sourcing' => Sourcing::ON_DEMAND,
        'supplier_reference' => 'TM-2212',
        'hs_code' => '8807',
        'origin_country' => 'CN',
    ]);

    $first = poOrder($tenant, OrderStatus::PENDING);
    poItem($first, $motor, 3);
    $second = poOrder($tenant, OrderStatus::CONFIRMED);
    poItem($second, $motor, 2);

    $result = app(SupplierOrderService::class)->aggregateOnDemand();

    expect($result['totals'])->toBe([
        'sku_count' => 1,
        'units' => 5,
        'orders' => 2,
    ]);

    $line = $result['lines'][0];
    expect($line['total_quantity'])->toBe(5)
        ->and($line['order_count'])->toBe(2)
        ->and($line['supplier_reference'])->toBe('TM-2212')
        ->and($line['hs_code'])->toBe('8807');
});

it('excludes in-stock items and terminal orders', function () {
    $tenant = poTenant();

    // In-stock product → excluded regardless of order status.
    $stock = poProduct($tenant, ['sourcing' => Sourcing::IN_STOCK, 'sku' => 'STOCK-SKU']);
    $stockOrder = poOrder($tenant, OrderStatus::PENDING);
    poItem($stockOrder, $stock, 10);

    // On-demand product but order is already delivered → excluded.
    $deliveredMotor = poProduct($tenant, ['sourcing' => Sourcing::ON_DEMAND, 'sku' => 'DONE-SKU']);
    $deliveredOrder = poOrder($tenant, OrderStatus::DELIVERED);
    poItem($deliveredOrder, $deliveredMotor, 4);

    $result = app(SupplierOrderService::class)->aggregateOnDemand();

    expect($result['lines'])->toBe([])
        ->and($result['totals']['sku_count'])->toBe(0)
        ->and($result['totals']['units'])->toBe(0);
});
