<?php

use App\Actions\Cart\CartReadAction;
use App\Enums\Sourcing;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Str;

function cartReadTenant(): Tenant
{
    return Tenant::query()->create([
        'name' => 'HTAShop',
        'slug' => 'cart-'.Str::lower(Str::random(8)),
        'is_active' => true,
    ]);
}

function cartReadProduct(Tenant $tenant, array $attributes = []): Product
{
    $organization = Organization::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'HTAShop Store',
        'slug' => 'cart-org-'.Str::lower(Str::random(8)),
    ]);

    return Product::query()->create(array_merge([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'name' => 'BLDC Motor',
        'slug' => 'cart-prod-'.Str::lower(Str::random(8)),
        'status' => 'active',
        'is_active' => true,
        'metadata' => ['price' => 1000, 'currency' => 'PKR'],
    ], $attributes));
}

function cartReadCart(string $sessionId): Cart
{
    return Cart::query()->create([
        'session_id' => $sessionId,
        'currency' => 'PKR',
        'status' => 'active',
    ]);
}

function cartReadItem(Cart $cart, Product $product): CartItem
{
    return CartItem::query()->create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 1000,
        'currency' => 'PKR',
    ]);
}

it('flags cart items that require advance payment', function () {
    $tenant = cartReadTenant();
    $product = cartReadProduct($tenant, ['sourcing' => Sourcing::ON_DEMAND]);
    $cart = cartReadCart('cart-od');
    cartReadItem($cart, $product);

    $data = app(CartReadAction::class)->handle($cart);

    expect($data['items'][0]['requires_advance_payment'])->toBeTrue();
});

it('does not flag in-stock cart items', function () {
    $tenant = cartReadTenant();
    $product = cartReadProduct($tenant, ['sourcing' => Sourcing::IN_STOCK]);
    $cart = cartReadCart('cart-stock');
    cartReadItem($cart, $product);

    $data = app(CartReadAction::class)->handle($cart);

    expect($data['items'][0]['requires_advance_payment'])->toBeFalse();
});
