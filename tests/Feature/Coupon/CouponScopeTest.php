<?php

use App\Enums\CouponType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Coupon\CouponException;
use App\Services\Coupon\CouponScope;
use App\Services\Coupon\CouponService;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Coupons are scoped to an organization, a tenant or the platform.
 *
 * The scope decides two things: who may *manage* a code, and which code a
 * shopper's entered code *resolves to* when the same string exists in several
 * scopes. The second is the subtle one — precedence is organization → tenant →
 * global — so it gets its own section.
 */
beforeEach(function () {
    ensurePersonalAccessClient();

    $role = Role::findOrCreate('admin', 'api');
    $admin = User::factory()->create();
    $admin->assignRole($role);

    $this->withToken(signInDevice($admin->email)['access_token']);
});

function scopeTenant(string $name = 'Tenant'): Tenant
{
    return Tenant::query()->create([
        'name' => $name,
        'slug' => 'scope-tenant-'.Str::lower(Str::random(8)),
        'is_active' => true,
    ]);
}

function scopeOrganization(Tenant $tenant, string $name = 'Organization'): Organization
{
    return Organization::query()->create([
        'tenant_id' => $tenant->id,
        'name' => $name,
        'slug' => 'scope-org-'.Str::lower(Str::random(8)),
        'type' => 'vendor',
        'is_active' => true,
    ]);
}

function scopeMember(User $user, Tenant $tenant, Organization $organization): Membership
{
    return Membership::query()->create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_primary' => true,
        'is_active' => true,
    ]);
}

function scopeCouponRow(array $attributes = []): Coupon
{
    return Coupon::query()->create(array_merge([
        'code' => 'SCOPE'.Str::upper(Str::random(6)),
        'type' => CouponType::FIXED,
        'value' => 100,
        'is_active' => true,
    ], $attributes));
}

describe('scoped management', function () {
    it('lets the same code exist globally and inside a tenant', function () {
        $tenant = scopeTenant();

        scopeCouponRow(['code' => 'EID10']);

        $this->postJson('/api/v1/admin/coupons', [
            'code' => 'EID10',
            'type' => CouponType::PERCENT,
            'value' => 10,
            'tenant_id' => $tenant->id,
        ])->assertCreated()->assertJsonPath('data.scope', 'tenant');

        expect(Coupon::query()->where('code', 'EID10')->count())->toBe(2);
    });

    it('lets two tenants run the same code', function () {
        $first = scopeTenant('First');
        $second = scopeTenant('Second');

        $this->postJson('/api/v1/admin/coupons', [
            'code' => 'EID10', 'type' => CouponType::PERCENT, 'value' => 10, 'tenant_id' => $first->id,
        ])->assertCreated();

        $this->postJson('/api/v1/admin/coupons', [
            'code' => 'EID10', 'type' => CouponType::PERCENT, 'value' => 10, 'tenant_id' => $second->id,
        ])->assertCreated();
    });

    it('refuses a duplicate code within the same scope', function () {
        $tenant = scopeTenant();

        scopeCouponRow(['code' => 'DUP10', 'tenant_id' => $tenant->id]);

        $this->postJson('/api/v1/admin/coupons', [
            'code' => 'DUP10', 'type' => CouponType::PERCENT, 'value' => 10, 'tenant_id' => $tenant->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    });

    it('derives the tenant from the organization', function () {
        $tenant = scopeTenant();
        $organization = scopeOrganization($tenant);

        $response = $this->postJson('/api/v1/admin/coupons', [
            'code' => 'ORGDISC',
            'type' => CouponType::FIXED,
            'value' => 250,
            'organization_id' => $organization->id,
        ])->assertCreated();

        expect($response->json('data.scope'))->toBe('organization')
            ->and($response->json('data.tenant_id'))->toBe($tenant->id)
            ->and($response->json('data.organization_id'))->toBe($organization->id);
    });

    it('refuses an organization that is not in the chosen tenant', function () {
        $tenant = scopeTenant('A');
        $other = scopeTenant('B');
        $organization = scopeOrganization($other);

        $this->postJson('/api/v1/admin/coupons', [
            'code' => 'BADORG',
            'type' => CouponType::FIXED,
            'value' => 100,
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('organization_id');
    });

    it('filters the list by scope', function () {
        $tenant = scopeTenant();
        $organization = scopeOrganization($tenant);

        scopeCouponRow(['code' => 'GLOBAL1']);
        scopeCouponRow(['code' => 'TENANT1', 'tenant_id' => $tenant->id]);
        scopeCouponRow(['code' => 'ORG1', 'tenant_id' => $tenant->id, 'organization_id' => $organization->id]);

        $global = $this->getJson('/api/v1/admin/coupons?scope=global')->json('data.data');
        $tenanted = $this->getJson('/api/v1/admin/coupons?scope=tenant')->json('data.data');
        $orgs = $this->getJson('/api/v1/admin/coupons?scope=organization')->json('data.data');

        expect($global)->toHaveCount(1)->and($global[0]['code'])->toBe('GLOBAL1')
            ->and($tenanted)->toHaveCount(1)->and($tenanted[0]['code'])->toBe('TENANT1')
            ->and($orgs)->toHaveCount(1)->and($orgs[0]['code'])->toBe('ORG1');
    });

    it('offers tenants and organizations for the scope picker', function () {
        $tenant = scopeTenant('Picker Tenant');
        $organization = scopeOrganization($tenant, 'Picker Org');

        $response = $this->getJson('/api/v1/admin/coupons/scopes')->assertOk();

        expect($response->json('data.tenants'))->toHaveCount(1)
            ->and($response->json('data.tenants.0.name'))->toBe('Picker Tenant')
            ->and($response->json('data.organizations.0.name'))->toBe('Picker Org')
            ->and($response->json('data.organizations.0.tenant_id'))->toBe($tenant->id);

        expect($organization->id)->toBeInt();
    });
});

describe('checkout resolution', function () {
    it('prefers the organization code over the tenant and global ones', function () {
        $tenant = scopeTenant();
        $organization = scopeOrganization($tenant);

        scopeCouponRow(['code' => 'STACK', 'value' => 10]);
        scopeCouponRow(['code' => 'STACK', 'value' => 20, 'tenant_id' => $tenant->id]);
        scopeCouponRow(['code' => 'STACK', 'value' => 30, 'tenant_id' => $tenant->id, 'organization_id' => $organization->id]);

        $coupon = app(CouponService::class)->resolve('STACK', 1000, null, null, $tenant->id, $organization->id);

        expect((float) $coupon->value)->toBe(30.0);
    });

    it('prefers the tenant code over the global one', function () {
        $tenant = scopeTenant();

        scopeCouponRow(['code' => 'STACK', 'value' => 10]);
        scopeCouponRow(['code' => 'STACK', 'value' => 20, 'tenant_id' => $tenant->id]);

        $coupon = app(CouponService::class)->resolve('STACK', 1000, null, null, $tenant->id, null);

        expect((float) $coupon->value)->toBe(20.0);
    });

    it('treats a code scoped to another tenant as not found', function () {
        $mine = scopeTenant('Mine');
        $theirs = scopeTenant('Theirs');

        scopeCouponRow(['code' => 'PRIVATE', 'tenant_id' => $theirs->id]);

        expect(fn () => app(CouponService::class)
            ->resolve('PRIVATE', 1000, null, null, $mine->id, null))
            ->toThrow(CouponException::class, CouponException::NOT_FOUND);
    });

    it('falls back to a global code for a shopper with no scope', function () {
        scopeCouponRow(['code' => 'PUBLIC', 'value' => 50]);

        $coupon = app(CouponService::class)->resolve('PUBLIC', 1000);

        expect((float) $coupon->value)->toBe(50.0);
    });
});

describe('merchant scope', function () {
    it('lists only the caller organization codes', function () {
        $tenant = scopeTenant();
        $mine = scopeOrganization($tenant, 'Mine');
        $theirs = scopeOrganization($tenant, 'Theirs');

        $merchant = User::factory()->create();
        scopeMember($merchant, $tenant, $mine);

        scopeCouponRow(['code' => 'MINE', 'tenant_id' => $tenant->id, 'organization_id' => $mine->id]);
        scopeCouponRow(['code' => 'THEIRS', 'tenant_id' => $tenant->id, 'organization_id' => $theirs->id]);
        scopeCouponRow(['code' => 'GLOBALX']);

        $this->app['auth']->forgetGuards();

        $response = $this->withToken(signInDevice($merchant->email)['access_token'])
            ->getJson('/api/v1/coupons')
            ->assertOk();

        expect($response->json('data.data'))->toHaveCount(1)
            ->and($response->json('data.data.0.code'))->toBe('MINE');
    });

    it('forces the caller scope when creating, ignoring tenant_id in the payload', function () {
        $tenant = scopeTenant();
        $mine = scopeOrganization($tenant, 'Mine');
        $other = scopeTenant('Other');

        $merchant = User::factory()->create();
        scopeMember($merchant, $tenant, $mine);

        $this->app['auth']->forgetGuards();

        $response = $this->withToken(signInDevice($merchant->email)['access_token'])
            ->postJson('/api/v1/coupons', [
                'code' => 'FORCED',
                'type' => CouponType::FIXED,
                'value' => 100,
                'tenant_id' => $other->id,
            ])
            ->assertCreated();

        expect($response->json('data.organization_id'))->toBe($mine->id)
            ->and($response->json('data.tenant_id'))->toBe($tenant->id);
    });

    it('hides another merchant coupon behind a 404', function () {
        $tenant = scopeTenant();
        $mine = scopeOrganization($tenant, 'Mine');
        $theirs = scopeOrganization($tenant, 'Theirs');

        $merchant = User::factory()->create();
        scopeMember($merchant, $tenant, $mine);

        $foreign = scopeCouponRow([
            'code' => 'NOTMINE',
            'tenant_id' => $tenant->id,
            'organization_id' => $theirs->id,
        ]);

        $this->app['auth']->forgetGuards();

        $token = signInDevice($merchant->email)['access_token'];

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson("/api/v1/coupons/{$foreign->uuid}")
            ->assertNotFound();

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->patchJson("/api/v1/coupons/{$foreign->uuid}", ['value' => 999])
            ->assertNotFound();
    });

    it('refuses a signed-in user with no membership', function () {
        $user = User::factory()->create();

        $this->app['auth']->forgetGuards();

        $this->withToken(signInDevice($user->email)['access_token'])
            ->getJson('/api/v1/coupons')
            ->assertForbidden();
    });
});

describe('checkout scope integration', function () {
    it('applies an organization coupon through the quote endpoint and hides another organization code', function () {
        config([
            'shipping.enabled' => true,
            'shipping.rates.PKR' => ['flat_rate' => 0, 'free_over' => null],
            'shipping.default' => ['flat_rate' => 0, 'free_over' => null],
            'shipping.tax.enabled' => false,
        ]);

        $tenant = scopeTenant();
        $organization = scopeOrganization($tenant, 'Buyer Org');

        $buyer = User::factory()->create();
        scopeMember($buyer, $tenant, $organization);

        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'name' => 'Scoped Widget',
            'slug' => 'scoped-prod-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'is_active' => true,
            'metadata' => ['price' => 1000, 'currency' => 'PKR'],
        ]);

        $sessionId = 'scope-quote-'.Str::lower(Str::random(6));

        $cart = Cart::query()->create([
            'session_id' => $sessionId,
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1000,
            'currency' => 'PKR',
        ]);

        scopeCouponRow([
            'code' => 'ORGSCOPE',
            'value' => 250,
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
        ]);

        // A code belonging to a sibling organization must not be usable here.
        $otherOrganization = scopeOrganization($tenant, 'Other Org');
        scopeCouponRow([
            'code' => 'HIDDENORG',
            'value' => 999,
            'tenant_id' => $tenant->id,
            'organization_id' => $otherOrganization->id,
        ]);

        $this->app['auth']->forgetGuards();
        $token = signInDevice($buyer->email)['access_token'];
        $this->app['auth']->forgetGuards();

        $response = $this->withToken($token)
            ->withHeader('X-Cart-Token', $sessionId)
            ->postJson('/api/v1/checkout/quote', ['coupon_code' => 'ORGSCOPE'])
            ->assertOk();

        expect((float) $response->json('data.discount'))->toBe(250.0)
            ->and($response->json('data.coupon_code'))->toBe('ORGSCOPE');

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->withHeader('X-Cart-Token', $sessionId)
            ->postJson('/api/v1/checkout/quote', ['coupon_code' => 'HIDDENORG'])
            ->assertStatus(422);
    });
});

describe('the scope value object', function () {
    it('resolves a membership to its organization scope', function () {
        $tenant = scopeTenant();
        $organization = scopeOrganization($tenant);
        $user = User::factory()->create();
        scopeMember($user, $tenant, $organization);

        $scope = CouponScope::forUser($user->fresh());

        expect($scope)->not->toBeNull()
            ->and($scope->tenantId)->toBe($tenant->id)
            ->and($scope->organizationId)->toBe($organization->id)
            ->and($scope->isGlobal())->toBeFalse();
    });

    it('resolves no membership to no scope', function () {
        expect(CouponScope::forUser(User::factory()->create()))->toBeNull();
    });
});
