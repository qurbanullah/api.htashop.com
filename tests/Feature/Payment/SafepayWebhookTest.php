<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Gateways\Safepay\SafepaySignature;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    configureSafepay();
});

/**
 * Deliver a webhook the way Safepay does: a JSON body with an HMAC-SHA512
 * signature header over those exact bytes.
 *
 * The signature is computed over the same encoding the test client sends, so
 * the bytes on the wire and the bytes we signed are identical.
 */
function postSafepayWebhook(array $event, ?string $signature = null, string $secret = 'test_webhook_secret')
{
    $signature ??= safepaySignature(json_encode($event), $secret);

    return test()
        ->withHeaders([SafepaySignature::HEADER => $signature])
        ->postJson('/api/v1/payments/webhooks/safepay', $event);
}

function safepayEvent(string $type, array $data = []): array
{
    return ['type' => $type, 'data' => $data];
}

it('marks a payment paid and confirms the order', function () {
    $order = paymentOrder();
    $payment = paymentRecord($order, ['payment_method' => PaymentMethod::SAFEPAY]);
    $order->update(['status' => OrderStatus::PENDING]);

    postSafepayWebhook(safepayEvent('payment.succeeded', [
        'id' => 'txn_1',
        'state' => 'PAID',
        'metadata' => ['payment_uuid' => $payment->uuid],
    ]))->assertOk()->assertJsonPath('data.handled', true);

    expect($payment->fresh()->status)->toBe(PaymentStatus::PAID)
        ->and($payment->fresh()->transaction_reference)->toBe('txn_1')
        ->and($order->fresh()->status)->toBe(OrderStatus::CONFIRMED)
        ->and($order->fresh()->confirmed_at)->not->toBeNull();

    $charge = $payment->transactions()->where('type', 'charge')->firstOrFail();

    expect($charge->status)->toBe('succeeded')
        ->and((float) $charge->amount)->toBe(1500.00);
});

it('rejects a webhook whose signature does not verify', function () {
    $payment = paymentRecord(paymentOrder(), ['payment_method' => PaymentMethod::SAFEPAY]);

    postSafepayWebhook(
        safepayEvent('payment.succeeded', ['metadata' => ['payment_uuid' => $payment->uuid]]),
        'deadbeef',
    )->assertStatus(400);

    expect($payment->fresh()->status)->toBe(PaymentStatus::PENDING);
});

it('rejects a webhook signed with the wrong secret', function () {
    $payment = paymentRecord(paymentOrder(), ['payment_method' => PaymentMethod::SAFEPAY]);

    postSafepayWebhook(
        safepayEvent('payment.succeeded', ['metadata' => ['payment_uuid' => $payment->uuid]]),
        null,
        'not-the-secret',
    )->assertStatus(400);

    expect($payment->fresh()->status)->toBe(PaymentStatus::PENDING);
});

it('rejects a webhook when no webhook secret is configured', function () {
    configureSafepay(['webhook_secret' => null]);

    postSafepayWebhook(safepayEvent('payment.succeeded'))
        ->assertStatus(400);
});

it('accepts a webhook even after the gateway has been switched off', function () {
    $payment = paymentRecord(paymentOrder(), ['payment_method' => PaymentMethod::SAFEPAY]);

    configureSafepay(['enabled' => false]);

    postSafepayWebhook(safepayEvent('payment.succeeded', [
        'metadata' => ['payment_uuid' => $payment->uuid],
    ]))->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::PAID);
});

it('is idempotent when the same event is delivered twice', function () {
    $order = paymentOrder();
    $payment = paymentRecord($order, ['payment_method' => PaymentMethod::SAFEPAY]);

    $event = safepayEvent('payment.succeeded', [
        'id' => 'txn_9',
        'metadata' => ['payment_uuid' => $payment->uuid],
    ]);

    postSafepayWebhook($event)->assertOk();
    postSafepayWebhook($event)->assertOk();

    expect($payment->transactions()->where('type', 'charge')->where('status', 'succeeded')->count())->toBe(1);
});

it('does not un-pay a payment when a late failure arrives', function () {
    $payment = paymentRecord(paymentOrder(), [
        'payment_method' => PaymentMethod::SAFEPAY,
        'status' => PaymentStatus::PAID,
    ]);

    postSafepayWebhook(safepayEvent('payment.failed', [
        'metadata' => ['payment_uuid' => $payment->uuid],
    ]))->assertOk()->assertJsonPath('data.handled', true);

    expect($payment->fresh()->status)->toBe(PaymentStatus::PAID);
});

it('marks a payment failed without cancelling the order', function () {
    $order = paymentOrder();
    $payment = paymentRecord($order, ['payment_method' => PaymentMethod::SAFEPAY]);

    postSafepayWebhook(safepayEvent('payment.failed', [
        'metadata' => ['payment_uuid' => $payment->uuid],
    ]))->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::FAILED)
        ->and($order->fresh()->status)->toBe(OrderStatus::PENDING);
});

it('matches a webhook that only carries the tracker token', function () {
    $payment = paymentRecord(paymentOrder(), [
        'payment_method' => PaymentMethod::SAFEPAY,
        'transaction_reference' => 'trk_lookup',
    ]);

    postSafepayWebhook(safepayEvent('payment.succeeded', [
        'tracker' => ['token' => 'trk_lookup'],
    ]))->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::PAID);
});

it('accepts but does not act on an event it cannot attribute', function () {
    postSafepayWebhook(safepayEvent('payment.succeeded', ['metadata' => ['payment_uuid' => 'nope']]))
        ->assertOk()
        ->assertJsonPath('data.handled', false);
});

it('matches an event whose metadata values are objects', function () {
    // The reporter returns metadata as `{key, value, …}` objects and the
    // webhook has been observed in both shapes. An object handed straight to a
    // query is not a scalar, so this must be unwrapped before matching.
    $order = paymentOrder();
    $payment = paymentRecord($order, ['payment_method' => PaymentMethod::SAFEPAY]);

    postSafepayWebhook(safepayEvent('payment.succeeded', [
        'metadata' => [
            'order_id' => ['key' => 'order_id', 'value' => $order->uuid],
        ],
    ]))->assertOk()->assertJsonPath('data.handled', true);

    expect($payment->fresh()->status)->toBe(PaymentStatus::PAID);
});

it('returns 404 for an unknown gateway', function () {
    $event = safepayEvent('payment.succeeded');

    test()
        ->withHeaders([SafepaySignature::HEADER => safepaySignature(json_encode($event))])
        ->postJson('/api/v1/payments/webhooks/not-a-gateway', $event)
        ->assertStatus(404);
});
