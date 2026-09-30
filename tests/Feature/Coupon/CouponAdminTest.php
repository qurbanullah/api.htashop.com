<?php

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Admin management of discount codes.
 *
 * The rules that decide whether a code is *usable* are covered by
 * `tests/Feature/Checkout/CheckoutPricingTest.php`; this file covers managing
 * the catalogue behind `/admin/coupons`.
 */
beforeEach(function () {
    ensurePersonalAccessClient();

    $role = Role::findOrCreate('admin', 'api');
    $admin = User::factory()->create();
    $admin->assignRole($role);

    $this->withToken(signInDevice($admin->email)['access_token']);
});

/** A coupon row created directly, so the tests exercise the API, not the API. */
function couponRow(array $attributes = []): Coupon
{
    return Coupon::query()->create(array_merge([
        'code' => 'SAVE'.Str::upper(Str::random(6)),
        'label' => 'Test coupon',
        'type' => CouponType::FIXED,
        'value' => 100,
        'is_active' => true,
    ], $attributes));
}

function couponPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'EID10',
        'label' => 'Eid sale 10%',
        'type' => CouponType::PERCENT,
        'value' => 10,
        'min_order_amount' => 2000,
        'max_discount_amount' => 1000,
        'usage_limit' => 500,
        'per_user_limit' => 1,
        'is_active' => true,
    ], $overrides);
}

describe('listing', function () {
    it('lists coupons with their redemption count', function () {
        $coupon = couponRow(['code' => 'LISTME']);

        $response = $this->getJson('/api/v1/admin/coupons')->assertOk();

        expect($response->json('data.data'))->toHaveCount(1)
            ->and($response->json('data.data.0.code'))->toBe('LISTME')
            ->and($response->json('data.data.0.redemptions_count'))->toBe(0)
            ->and($response->json('data.data.0.status'))->toBe('active')
            ->and($response->json('data.pagination.total'))->toBe(1);

        expect($coupon->uuid)->not->toBeEmpty();
    });

    it('filters by search, type and active flag', function () {
        couponRow(['code' => 'FINDME', 'type' => CouponType::FIXED]);
        couponRow(['code' => 'OTHER', 'type' => CouponType::PERCENT]);
        couponRow(['code' => 'DISABLED', 'is_active' => false]);

        expect($this->getJson('/api/v1/admin/coupons?search=FIND')->json('data.data'))
            ->toHaveCount(1)
            ->and($this->getJson('/api/v1/admin/coupons?search=FIND')->json('data.data.0.code'))
            ->toBe('FINDME');

        expect($this->getJson('/api/v1/admin/coupons?type=percent')->json('data.data'))
            ->toHaveCount(1);

        expect($this->getJson('/api/v1/admin/coupons?is_active=0')->json('data.data'))
            ->toHaveCount(1)
            ->and($this->getJson('/api/v1/admin/coupons?is_active=0')->json('data.data.0.code'))
            ->toBe('DISABLED');
    });

    it('reports headline counts', function () {
        couponRow(['is_active' => true]);
        couponRow(['is_active' => false]);
        couponRow(['starts_at' => now()->addDay()]);
        couponRow(['ends_at' => now()->subDay()]);
        couponRow(['usage_limit' => 1, 'used_count' => 1]);

        $stats = $this->getJson('/api/v1/admin/coupons/statistics')->assertOk()->json('data');

        expect($stats['total'])->toBe(5)
            ->and($stats['active'])->toBe(1)
            ->and($stats['scheduled'])->toBe(1)
            ->and($stats['expired'])->toBe(1)
            ->and($stats['exhausted'])->toBe(1);
    });

    it('404s for an unknown coupon', function () {
        $this->getJson('/api/v1/admin/coupons/'.Str::uuid())->assertNotFound();
    });
});

describe('creating', function () {
    it('creates a coupon and normalises the code', function () {
        $response = $this->postJson('/api/v1/admin/coupons', couponPayload(['code' => 'eid10']))
            ->assertCreated();

        expect($response->json('data.code'))->toBe('EID10')
            ->and($response->json('data.status'))->toBe('active')
            ->and((float) $response->json('data.value'))->toBe(10.0)
            ->and($response->json('data.description'))->toBe('10% off');
    });

    it('refuses a duplicate code regardless of case', function () {
        couponRow(['code' => 'TAKEN']);

        $this->postJson('/api/v1/admin/coupons', couponPayload(['code' => 'taken']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    });

    it('refuses a percentage above 100', function () {
        $this->postJson('/api/v1/admin/coupons', couponPayload(['value' => 150]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('value');
    });

    it('refuses an end date before the start date', function () {
        $this->postJson('/api/v1/admin/coupons', couponPayload([
            'starts_at' => now()->toIso8601String(),
            'ends_at' => now()->subDay()->toIso8601String(),
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('ends_at');
    });

    it('refuses an unknown type', function () {
        $this->postJson('/api/v1/admin/coupons', couponPayload(['type' => 'magic']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    });
});

describe('updating', function () {
    it('updates a coupon without tripping its own uniqueness rule', function () {
        $coupon = couponRow(['code' => 'KEEP', 'value' => 100]);

        $response = $this->patchJson("/api/v1/admin/coupons/{$coupon->uuid}", [
            'code' => 'KEEP',
            'value' => 250,
        ])->assertOk();

        expect((float) $response->json('data.value'))->toBe(250.0)
            ->and($response->json('data.code'))->toBe('KEEP');
    });

    it('refuses a usage limit below what has already been used', function () {
        $coupon = couponRow(['usage_limit' => 10, 'used_count' => 4]);

        $this->patchJson("/api/v1/admin/coupons/{$coupon->uuid}", ['usage_limit' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors('usage_limit');
    });

    it('deactivates a coupon', function () {
        $coupon = couponRow(['is_active' => true]);

        $response = $this->patchJson("/api/v1/admin/coupons/{$coupon->uuid}", ['is_active' => false])
            ->assertOk();

        expect($response->json('data.is_active'))->toBeFalse()
            ->and($response->json('data.status'))->toBe('inactive');
    });
});

describe('deleting', function () {
    it('archives the coupon and keeps its redemptions', function () {
        $coupon = couponRow();
        CouponRedemption::query()->create([
            'coupon_id' => $coupon->id,
            'order_id' => paymentOrder()->id,
            'code' => $coupon->code,
            'amount' => 100,
            'currency' => 'PKR',
        ]);

        $this->deleteJson("/api/v1/admin/coupons/{$coupon->uuid}")->assertOk();

        expect(Coupon::query()->whereKey($coupon->id)->exists())->toBeFalse();
        $this->assertSoftDeleted('coupons', ['id' => $coupon->id]);
        // A discount that was actually given must stay auditable.
        expect(CouponRedemption::query()->where('coupon_id', $coupon->id)->count())->toBe(1);
    });
});

describe('redemptions', function () {
    it('lists who used the code', function () {
        $coupon = couponRow(['code' => 'USED']);
        $user = User::factory()->create(['name' => 'Ayesha Khan', 'email' => 'ayesha@example.test']);
        $order = paymentOrder();

        CouponRedemption::query()->create([
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'user_id' => $user->id,
            'code' => 'USED',
            'amount' => 250,
            'currency' => 'PKR',
        ]);

        $response = $this->getJson("/api/v1/admin/coupons/{$coupon->uuid}/redemptions")->assertOk();

        expect($response->json('data.data'))->toHaveCount(1)
            ->and($response->json('data.data.0.user_email'))->toBe('ayesha@example.test')
            ->and($response->json('data.data.0.order_number'))->toBe($order->order_number)
            ->and($response->json('data.data.0.guest'))->toBeFalse()
            ->and((float) $response->json('data.data.0.amount'))->toBe(250.0);
    });
});

describe('authorisation', function () {
    it('requires a token', function () {
        // The client carries the admin token by default, so blank it out for
        // this request rather than relying on a "fresh" client.
        $this->getJson('/api/v1/admin/coupons', ['Authorization' => ''])
            ->assertUnauthorized();
    });

    it('refuses a signed-in non-admin', function () {
        $user = User::factory()->create();
        $token = signInDevice($user->email)['access_token'];

        $this->app['auth']->forgetGuards();

        test()->withToken($token)->getJson('/api/v1/admin/coupons')->assertForbidden();
    });
});
