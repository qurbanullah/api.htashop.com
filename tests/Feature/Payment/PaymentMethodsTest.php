<?php

use App\Enums\PaymentMethod;
use App\Gateways\PaymentGatewayManager;
use App\Gateways\SafepayGateway;
use App\Models\Order;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('offers only cash on delivery when every hosted gateway is disabled', function () {
    config([
        'payment.enabled' => false,
        'payment.gateways' => [],
    ]);
    forgetPaymentGateways();

    $this->getJson('/api/v1/payments/methods')
        ->assertOk()
        ->assertJsonPath('data.methods', [
            ['method' => 'cod', 'label' => 'Cash on Delivery', 'requires_redirect' => false],
        ]);
});

it('offers safepay once it is switched on and configured', function () {
    configureSafepay();

    $this->getJson('/api/v1/payments/methods')
        ->assertOk()
        ->assertJsonFragment(['method' => 'safepay', 'requires_redirect' => true])
        ->assertJsonFragment(['method' => 'cod']);
});

it('withholds safepay while it has no credentials', function () {
    configureSafepay(['public_key' => null]);

    $methods = collect($this->getJson('/api/v1/payments/methods')->assertOk()->json('data.methods'))
        ->pluck('method');

    expect($methods)->toContain('cod')
        ->and($methods)->not->toContain('safepay');
});

it('withholds safepay for a currency it cannot settle', function () {
    configureSafepay(['currencies' => ['PKR']]);

    $methods = collect($this->getJson('/api/v1/payments/methods?currency=EUR')->assertOk()->json('data.methods'))
        ->pluck('method');

    expect($methods)->toContain('cod')
        ->and($methods)->not->toContain('safepay');
});

it('refuses to resolve a gateway that is disabled', function () {
    config([
        'payment.enabled' => false,
        'payment.gateways' => [
            'safepay' => [
                'driver' => SafepayGateway::class,
                'enabled' => true,
                'public_key' => 'sec_test',
                'secret_key' => 'sk_test',
                'currencies' => ['PKR'],
            ],
        ],
    ]);
    forgetPaymentGateways();

    $manager = app(PaymentGatewayManager::class);

    expect($manager->isAvailable(PaymentMethod::SAFEPAY))->toBeFalse()
        ->and($manager->isAvailable(PaymentMethod::COD))->toBeTrue()
        ->and(fn () => $manager->gateway(PaymentMethod::SAFEPAY))
        ->toThrow(InvalidArgumentException::class);
});

it('still resolves a disabled gateway so an in-flight payment can settle', function () {
    configureSafepay(['enabled' => false]);

    expect(app(PaymentGatewayManager::class)->implementation(PaymentMethod::SAFEPAY))
        ->toBeInstanceOf(SafepayGateway::class);
});

it('skips a gateway whose driver class does not exist', function () {
    config([
        'payment.enabled' => true,
        'payment.gateways' => [
            'jazzcash' => ['driver' => 'App\\Gateways\\JazzcashGateway', 'enabled' => true],
        ],
    ]);
    forgetPaymentGateways();

    $methods = collect($this->getJson('/api/v1/payments/methods')->assertOk()->json('data.methods'))
        ->pluck('method');

    expect($methods->all())->toBe(['cod']);
});

it('refuses a checkout that asks for a switched-off gateway', function () {
    // Availability is checked before anything is created, so a disabled method
    // cannot leave a half-finished order behind.
    config([
        'payment.enabled' => false,
        'payment.gateways' => [],
    ]);
    forgetPaymentGateways();

    $this->postJson('/api/v1/checkout', [
        'checkout_token' => 'token-for-a-disabled-gateway',
        'payment_method' => 'safepay',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('payment_method');

    expect(Order::query()->count())->toBe(0);
});
