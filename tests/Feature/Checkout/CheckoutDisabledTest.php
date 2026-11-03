<?php

namespace Tests\Feature\Checkout;

/**
 * Checkout is switched off until the live payment accounts are approved.
 * The API is fail-closed: the storefront also hides the CTA, but a direct
 * request must never place an order while the switch is off.
 */
beforeEach(function () {
    config([
        'checkout.enabled' => false,
        'checkout.disabled_message' => 'Checkout is paused for payment verification.',
    ]);
});

it('reports checkout as disabled', function () {
    $this->getJson('/api/v1/checkout/status')
        ->assertOk()
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.message', 'Checkout is paused for payment verification.');
});

it('blocks order placement and checkout quotes while disabled', function () {
    $this->postJson('/api/v1/checkout', [])
        ->assertStatus(503)
        ->assertJsonPath('success', false)
        ->assertJsonPath('checkout_enabled', false)
        ->assertJsonPath('message', 'Checkout is paused for payment verification.');

    $this->postJson('/api/v1/checkout/quote', [])
        ->assertStatus(503)
        ->assertJsonPath('checkout_enabled', false);
});

it('reports checkout as enabled when the switch is on', function () {
    config(['checkout.enabled' => true]);

    $this->getJson('/api/v1/checkout/status')
        ->assertOk()
        ->assertJsonPath('data.enabled', true);
});

it('keeps cart access available while checkout is disabled', function () {
    $this->getJson('/api/v1/cart')->assertOk();
});
