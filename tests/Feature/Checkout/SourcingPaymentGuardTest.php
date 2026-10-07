<?php

use App\Enums\Sourcing;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Str;

function guardTenant(): Tenant
{
    return Tenant::query()->create([
        'name' => 'HTAShop',
        'slug' => 'guard-'.Str::lower(Str::random(8)),
        'is_active' => true,
    ]);
}

function guardProduct(Tenant $tenant, array $attributes = []): Product
{
    $organization = Organization::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'HTAShop Store',
        'slug' => 'guard-org-'.Str::lower(Str::random(8)),
    ]);

    return Product::query()->create(array_merge([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'name' => 'Test Widget',
        'slug' => 'guard-prod-'.Str::lower(Str::random(8)),
        'status' => 'active',
        'is_active' => true,
        'metadata' => ['price' => 1000, 'currency' => 'PKR'],
    ], $attributes));
}

function guardCart(string $sessionId, string $currency = 'PKR'): Cart
{
    return Cart::query()->create([
        'session_id' => $sessionId,
        'currency' => $currency,
        'status' => 'active',
    ]);
}

function guardCartItem(Cart $cart, Product $product): CartItem
{
    return CartItem::query()->create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => (float) data_get($product->metadata, 'price', 0),
        'currency' => $cart->currency,
    ]);
}

function guardPayload(array $overrides = []): array
{
    return array_merge([
        'payment_method' => 'cod',
        'customer_name' => 'Test Customer',
        'customer_email' => 'buyer@example.test',
        'customer_phone' => '+923001234567',
        'shipping_address' => [
            'contact_name' => 'Test Customer',
            'phone' => '+923001234567',
            'address_line_1' => '263 Nishtar Block',
            'city' => 'Lahore',
            'state' => 'Punjab',
            'postal_code' => '54570',
        ],
    ], $overrides);
}

beforeEach(function () {
    config([
        'shipping.enabled' => true,
        'shipping.rates.PKR' => ['flat_rate' => 250, 'free_over' => 5000],
        'shipping.default' => ['flat_rate' => 0, 'free_over' => null],
        'shipping.tax.enabled' => false,
        'shipping.tax.rate' => 0,
    ]);
});

describe('import-on-demand payment guard', function () {
    it('rejects cash on delivery for an import-on-demand product', function () {
        $tenant = guardTenant();
        $product = guardProduct($tenant, ['sourcing' => Sourcing::ON_DEMAND, 'lead_time_days' => 18]);
        $cart = guardCart('guard-od');
        guardCartItem($cart, $product);

        $this->withHeader('X-Cart-Token', 'guard-od')
            ->postJson('/api/v1/checkout', guardPayload(['payment_method' => 'cod']))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.payment_method.0', 'Cash on delivery is not available for import-on-demand items. Please choose an online payment method.');
    });

    it('allows cash on delivery for in-stock products', function () {
        $tenant = guardTenant();
        $product = guardProduct($tenant, ['sourcing' => Sourcing::IN_STOCK]);
        $cart = guardCart('guard-stock');
        guardCartItem($cart, $product);

        $this->withHeader('X-Cart-Token', 'guard-stock')
            ->postJson('/api/v1/checkout', guardPayload(['payment_method' => 'cod']))
            ->assertCreated();
    });
});
