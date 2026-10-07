<?php

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Enums\Sourcing;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\DutyRate;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Coupon\CouponException;
use App\Services\Coupon\CouponService;
use App\Services\Order\OrderService;
use Illuminate\Support\Str;

/**
 * What a basket costs is decided on the server.
 *
 * Delivery comes from `config/shipping.php` and discounts from the coupon
 * table; nothing the client sends can change either. The quote endpoint and
 * order placement share one calculation, so the numbers the customer is shown
 * are the numbers they are charged — and a crafted request cannot discount
 * itself.
 */
beforeEach(function () {
    // Deterministic rates, independent of whatever the environment sets.
    config([
        'shipping.enabled' => true,
        'shipping.rates.PKR' => ['flat_rate' => 250, 'free_over' => 5000],
        'shipping.rates.USD' => ['flat_rate' => 10, 'free_over' => 150],
        'shipping.default' => ['flat_rate' => 0, 'free_over' => null],
        'shipping.tax.enabled' => false,
        'shipping.tax.rate' => 0,
    ]);
});

function ckTenant(): Tenant
{
    return Tenant::query()->create([
        'name' => 'HTAShop',
        'slug' => 'ck-'.Str::lower(Str::random(8)),
        'is_active' => true,
    ]);
}

function ckProduct(Tenant $tenant, float $price, array $attributes = []): Product
{
    $organization = Organization::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'HTAShop Store',
        'slug' => 'ck-org-'.Str::lower(Str::random(8)),
    ]);

    return Product::query()->create(array_merge([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'name' => 'Test Widget',
        'slug' => 'ck-prod-'.Str::lower(Str::random(8)),
        'status' => 'active',
        'is_active' => true,
        'metadata' => ['price' => $price, 'currency' => 'PKR'],
    ], $attributes));
}

function ckCart(string $sessionId, string $currency = 'PKR'): Cart
{
    return Cart::query()->create([
        'session_id' => $sessionId,
        'currency' => $currency,
        'status' => 'active',
    ]);
}

function ckCartItem(Cart $cart, Product $product, int $quantity = 1): CartItem
{
    return CartItem::query()->create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'unit_price' => (float) data_get($product->metadata, 'price', 0),
        'currency' => $cart->currency,
    ]);
}

function ckCoupon(array $attributes = []): Coupon
{
    return Coupon::query()->create(array_merge([
        'code' => 'SAVE'.Str::upper(Str::random(6)),
        'type' => CouponType::FIXED,
        'value' => 100,
        'is_active' => true,
    ], $attributes));
}

/**
 * A minimal, valid checkout body. Overrides let a test add a coupon or try to
 * smuggle in its own totals.
 */
function ckCheckoutPayload(array $overrides = []): array
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

describe('delivery charges', function () {
    it('charges the flat rate below the free-delivery threshold', function () {
        $tenant = ckTenant();
        $product = ckProduct($tenant, 1000);
        $cart = ckCart('sess-flat');
        ckCartItem($cart, $product, 2);

        $response = $this->withHeader('X-Cart-Token', 'sess-flat')
            ->postJson('/api/v1/checkout/quote')
            ->assertOk();

        expect((float) $response->json('data.subtotal'))->toBe(2000.0)
            ->and((float) $response->json('data.shipping_fee'))->toBe(250.0)
            ->and($response->json('data.free_shipping'))->toBeFalse()
            ->and((float) $response->json('data.amount_until_free_shipping'))->toBe(3000.0)
            ->and((float) $response->json('data.total'))->toBe(2250.0);
    });

    it('makes delivery free exactly at the threshold', function () {
        $tenant = ckTenant();
        $product = ckProduct($tenant, 2500);
        $cart = ckCart('sess-free');
        ckCartItem($cart, $product, 2);

        $response = $this->withHeader('X-Cart-Token', 'sess-free')
            ->postJson('/api/v1/checkout/quote')
            ->assertOk();

        expect((float) $response->json('data.subtotal'))->toBe(5000.0)
            ->and((float) $response->json('data.shipping_fee'))->toBe(0.0)
            ->and($response->json('data.free_shipping'))->toBeTrue()
            ->and($response->json('data.amount_until_free_shipping'))->toBeNull()
            ->and((float) $response->json('data.total'))->toBe(5000.0);
    });

    it('prices delivery in the cart currency', function () {
        $tenant = ckTenant();
        $product = ckProduct($tenant, 100, [
            'metadata' => ['price' => 100, 'currency' => 'USD'],
        ]);
        $cart = ckCart('sess-usd', 'USD');
        ckCartItem($cart, $product, 1);

        $response = $this->withHeader('X-Cart-Token', 'sess-usd')
            ->postJson('/api/v1/checkout/quote')
            ->assertOk();

        expect($response->json('data.currency'))->toBe('USD')
            ->and((float) $response->json('data.shipping_fee'))->toBe(10.0)
            ->and((float) $response->json('data.total'))->toBe(110.0);
    });
});

describe('coupon maths', function () {
    it('caps a percentage discount at the maximum and charges delivery after it', function () {
        $tenant = ckTenant();
        $product = ckProduct($tenant, 5000);
        $cart = ckCart('sess-percent');
        ckCartItem($cart, $product, 1);

        ckCoupon([
            'code' => 'TENOFF',
            'type' => CouponType::PERCENT,
            'value' => 10,
            'max_discount_amount' => 300,
            'min_order_amount' => 1000,
        ]);

        $response = $this->withHeader('X-Cart-Token', 'sess-percent')
            ->postJson('/api/v1/checkout/quote', ['coupon_code' => 'TENOFF'])
            ->assertOk();

        expect((float) $response->json('data.discount'))->toBe(300.0)
            ->and((float) $response->json('data.subtotal'))->toBe(5000.0)
            ->and((float) $response->json('data.shipping_fee'))->toBe(0.0)
            ->and((float) $response->json('data.total'))->toBe(4700.0)
            ->and($response->json('data.coupon_code'))->toBe('TENOFF');
    });

    it('never discounts more than the subtotal', function () {
        $tenant = ckTenant();
        $product = ckProduct($tenant, 1000);
        $cart = ckCart('sess-clamp');
        ckCartItem($cart, $product, 1);

        ckCoupon(['code' => 'BIGDISC', 'value' => 5000]);

        $response = $this->withHeader('X-Cart-Token', 'sess-clamp')
            ->postJson('/api/v1/checkout/quote', ['coupon_code' => 'BIGDISC'])
            ->assertOk();

        // The order cannot go negative; only the delivery charge remains.
        expect((float) $response->json('data.discount'))->toBe(1000.0)
            ->and((float) $response->json('data.total'))->toBe(250.0);
    });

    it('matches a code regardless of surrounding spaces or case', function () {
        $tenant = ckTenant();
        $product = ckProduct($tenant, 1000);
        $cart = ckCart('sess-case');
        ckCartItem($cart, $product, 1);

        ckCoupon(['code' => 'SAVE100', 'value' => 100]);

        $response = $this->withHeader('X-Cart-Token', 'sess-case')
            ->postJson('/api/v1/checkout/quote', ['coupon_code' => ' save100 '])
            ->assertOk();

        expect((float) $response->json('data.discount'))->toBe(100.0);
    });
});

describe('coupon rules', function () {
    it('reports an unknown code as a 422 with a reason token', function () {
        $tenant = ckTenant();
        $product = ckProduct($tenant, 1000);
        $cart = ckCart('sess-bad');
        ckCartItem($cart, $product, 1);

        expect(fn () => app(CouponService::class)->resolve('DOES-NOT-EXIST', 1000.0))
            ->toThrow(CouponException::class, CouponException::NOT_FOUND);

        $this->withHeader('X-Cart-Token', 'sess-bad')
            ->postJson('/api/v1/checkout/quote', ['coupon_code' => 'NOPE'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('reason', 'coupon_not_found')
            ->assertJsonPath('errors.coupon_code.0', 'coupon_not_found');
    });

    it('rejects every unusable coupon with a distinct reason token', function (array $attributes, string $reason) {
        $coupon = ckCoupon($attributes);

        expect(fn () => app(CouponService::class)->resolve($coupon->code, 1000.0))
            ->toThrow(CouponException::class, $reason);
    })->with([
        'switched off' => [['is_active' => false], CouponException::INACTIVE],
        'not started yet' => [['starts_at' => now()->addDay()], CouponException::NOT_STARTED],
        'already expired' => [['ends_at' => now()->subDay()], CouponException::EXPIRED],
        'below the minimum spend' => [['min_order_amount' => 5000], CouponException::MIN_ORDER],
        'usage limit reached' => [['usage_limit' => 1, 'used_count' => 1], CouponException::USAGE_LIMIT],
    ]);

    it('enforces a per-customer limit', function () {
        $user = User::factory()->create();
        $tenant = ckTenant();
        $coupon = ckCoupon(['per_user_limit' => 1]);

        $order = Order::query()->create([
            'tenant_id' => $tenant->id,
            'order_number' => 'ORD-CK-'.Str::upper(Str::random(6)),
            'status' => OrderStatus::PENDING,
            'total_amount' => 1000,
            'currency' => 'PKR',
        ]);

        $coupons = app(CouponService::class);
        $coupons->redeem($coupon, $order, 100, $user);

        expect(fn () => $coupons->resolve($coupon->code, 1000.0, $user))
            ->toThrow(CouponException::class, CouponException::ALREADY_USED);
    });
});

describe('checkout totals are server-authoritative', function () {
    it('ignores client-supplied shipping, tax and discount', function () {
        $tenant = ckTenant();
        $product = ckProduct($tenant, 1000);
        $cart = ckCart('sess-secure');
        ckCartItem($cart, $product, 1);

        $response = $this->withHeader('X-Cart-Token', 'sess-secure')
            ->postJson('/api/v1/checkout', ckCheckoutPayload([
                // A crafted request trying to make itself cheaper.
                'discount' => 9999,
                'shipping_fee' => 0,
                'tax' => 0,
            ]))
            ->assertCreated();

        expect((float) $response->json('data.order.subtotal'))->toBe(1000.0)
            ->and((float) $response->json('data.order.discount'))->toBe(0.0)
            ->and((float) $response->json('data.order.shipping_fee'))->toBe(250.0)
            ->and((float) $response->json('data.order.tax'))->toBe(0.0)
            ->and((float) $response->json('data.order.total_amount'))->toBe(1250.0);
    });

    it('applies a valid coupon and records exactly one redemption', function () {
        $tenant = ckTenant();
        $product = ckProduct($tenant, 1000);
        $cart = ckCart('sess-coupon');
        ckCartItem($cart, $product, 1);

        $coupon = ckCoupon(['code' => 'SAVE100', 'value' => 100, 'usage_limit' => 5]);

        $response = $this->withHeader('X-Cart-Token', 'sess-coupon')
            ->postJson('/api/v1/checkout', ckCheckoutPayload(['coupon_code' => 'save100']))
            ->assertCreated();

        expect((float) $response->json('data.order.discount'))->toBe(100.0)
            ->and((float) $response->json('data.order.total_amount'))->toBe(1150.0)
            ->and($coupon->fresh()->used_count)->toBe(1)
            ->and(CouponRedemption::query()->where('coupon_id', $coupon->id)->count())->toBe(1);
    });

    it('gives the coupon use back when the order is cancelled', function () {
        $tenant = ckTenant();
        $product = ckProduct($tenant, 1000);
        $cart = ckCart('sess-release');
        ckCartItem($cart, $product, 1);

        $coupon = ckCoupon(['code' => 'RELEASE', 'value' => 100, 'usage_limit' => 1]);

        $this->withHeader('X-Cart-Token', 'sess-release')
            ->postJson('/api/v1/checkout', ckCheckoutPayload(['coupon_code' => 'RELEASE']))
            ->assertCreated();

        expect($coupon->fresh()->used_count)->toBe(1);

        $order = Order::query()->firstOrFail();
        app(OrderService::class)->updateStatus($order, OrderStatus::CANCELLED);

        expect($coupon->fresh()->used_count)->toBe(0)
            ->and(CouponRedemption::query()->count())->toBe(0);

        // The released code works again on a fresh basket.
        $cart2 = ckCart('sess-release-2');
        $product2 = ckProduct($tenant, 1000);
        ckCartItem($cart2, $product2, 1);

        $this->withHeader('X-Cart-Token', 'sess-release-2')
            ->postJson('/api/v1/checkout/quote', ['coupon_code' => 'RELEASE'])
            ->assertOk()
            ->assertJsonPath('data.discount', 100);
    });
});

describe('customs duty estimate', function () {
    it('estimates duty for import-on-demand items with a matching HS code', function () {
        DutyRate::query()->create(['hs_code' => '8807', 'customs_duty' => 10]);

        $tenant = ckTenant();
        $product = ckProduct($tenant, 1000, [
            'sourcing' => Sourcing::ON_DEMAND,
            'hs_code' => '8807.30.00',
        ]);
        $cart = ckCart('duty-od');
        ckCartItem($cart, $product, 2);

        $response = $this->withHeader('X-Cart-Token', 'duty-od')
            ->postJson('/api/v1/checkout/quote')
            ->assertOk();

        // 2 × 1000 = 2000 subtotal; duty = 10% of 2000 = 200.
        expect((float) $response->json('data.duty_estimate'))->toBe(200.0)
            ->and((float) $response->json('data.landed_cost'))->toBeGreaterThan((float) $response->json('data.total'));
    });

    it('does not estimate duty for in-stock items', function () {
        DutyRate::query()->create(['hs_code' => '8807', 'customs_duty' => 10]);

        $tenant = ckTenant();
        $product = ckProduct($tenant, 1000, ['hs_code' => '8807.30.00']);
        $cart = ckCart('duty-stock');
        ckCartItem($cart, $product, 2);

        $response = $this->withHeader('X-Cart-Token', 'duty-stock')
            ->postJson('/api/v1/checkout/quote')
            ->assertOk();

        expect((float) $response->json('data.duty_estimate'))->toBe(0.0);
    });

    it('estimates zero duty without a matching HS code', function () {
        $tenant = ckTenant();
        $product = ckProduct($tenant, 1000, ['sourcing' => Sourcing::ON_DEMAND]);
        $cart = ckCart('duty-nohs');
        ckCartItem($cart, $product, 1);

        $response = $this->withHeader('X-Cart-Token', 'duty-nohs')
            ->postJson('/api/v1/checkout/quote')
            ->assertOk();

        expect((float) $response->json('data.duty_estimate'))->toBe(0.0);
    });
});
