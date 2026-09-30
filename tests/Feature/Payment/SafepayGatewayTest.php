<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Gateways\SafepayGateway;
use App\Services\Payment\PaymentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Http::preventStrayRequests();
    configureSafepay();
});

/**
 * Stand in for the three Safepay endpoints the gateway calls, in the order it
 * calls them.
 */
function fakeSafepaySession(): void
{
    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/client/passport/v1/token')) {
            // The real endpoint answers with a bare string under `data`.
            return Http::response(['data' => 'tbt_123'], 201);
        }

        if (str_ends_with($url, '/metadata')) {
            return Http::response(['data' => ['tracker' => ['token' => 'trk_123']]], 200);
        }

        if (str_contains($url, '/order/payments/v3/')) {
            return Http::response(['data' => ['tracker' => ['token' => 'trk_123']]], 201);
        }

        return Http::response(['status' => ['errors' => ['unmatched '.$url]]], 404);
    });
}

it('opens a session and returns the hosted checkout url', function () {
    fakeSafepaySession();

    $order = paymentOrder();
    $result = app(SafepayGateway::class)->initialize($order);

    expect($result->requiresRedirect)->toBeTrue()
        ->and($result->redirectUrl)->toContain('tracker=trk_123')
        ->and($result->redirectUrl)->toContain('tbt=tbt_123')
        ->and($result->redirectUrl)->toContain('environment=sandbox')
        // `source` selects the hosted page's completion handler; an unrecognised
        // value leaves the paid customer stranded on Safepay's completion page.
        ->and($result->redirectUrl)->toContain('source=hosted')
        ->and($result->redirectUrl)->toContain('/embedded?')
        ->and($result->redirectUrl)->toContain('order_id='.$order->uuid);

    $payment = $order->payments()->firstOrFail();

    expect($payment->payment_method)->toBe(PaymentMethod::SAFEPAY)
        ->and($payment->status)->toBe(PaymentStatus::PENDING)
        ->and($payment->transaction_reference)->toBe('trk_123')
        ->and($payment->gateway_payload['checkout_url'])->toBe($result->redirectUrl);
});

it('defaults the checkout source to a value safepay recognises', function () {
    // Read the file's own default rather than the test override, so a
    // well-meaning change (e.g. to `custom`) fails here instead of silently
    // stranding paid customers on Safepay's completion page.
    $defaults = require config_path('payment.php');

    expect($defaults['gateways']['safepay']['source'])->toBe('hosted');
});

it('sends the public key, the intent and the amount in minor units', function () {
    fakeSafepaySession();

    app(SafepayGateway::class)->initialize(paymentOrder());

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/order/payments/v3/') || str_ends_with($request->url(), '/metadata')) {
            return false;
        }

        return $request->data() === [
            'merchant_api_key' => 'sec_test_public_key',
            'intent' => 'CYBERSOURCE',
            'mode' => 'payment',
            'currency' => 'PKR',
            'amount' => 150000,
        ];
    });
});

it('authenticates with the merchant secret header', function () {
    fakeSafepaySession();

    app(SafepayGateway::class)->initialize(paymentOrder());

    Http::assertSent(fn ($request) => $request->header('X-SFPY-MERCHANT-SECRET')[0] === 'test_secret_key');
});

it('sends only the metadata keys safepay accepts', function () {
    fakeSafepaySession();

    $order = paymentOrder();
    app(SafepayGateway::class)->initialize($order);

    Http::assertSent(function ($request) use ($order) {
        if (! str_ends_with($request->url(), '/metadata')) {
            return false;
        }

        $data = $request->data()['data'] ?? [];
        $keys = array_keys($data);
        sort($keys);

        // Safepay 400s on any other key (`payment_uuid`, `order_number`, …),
        // so only these two may ever be sent.
        return $keys === ['order_id', 'source']
            && ($data['order_id'] ?? null) === $order->uuid;
    });
});

it('still opens the checkout when safepay rejects the metadata', function () {
    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/client/passport/v1/token')) {
            return Http::response(['data' => 'tbt_123'], 201);
        }

        if (str_ends_with($url, '/metadata')) {
            return Http::response([
                'data' => null,
                'status' => ['errors' => ['could not set metadata: unsupported meta key order_id']],
            ], 400);
        }

        return Http::response(['data' => ['tracker' => ['token' => 'trk_123']]], 201);
    });

    // Metadata is an optimisation, not a step in taking money: a rejected key
    // must never stop the customer reaching the payment page.
    $result = app(SafepayGateway::class)->initialize(paymentOrder());

    expect($result->requiresRedirect)->toBeTrue()
        ->and($result->redirectUrl)->toContain('tracker=trk_123')
        ->and($result->redirectUrl)->toContain('tbt=tbt_123');
});

it('reuses the pending payment when checkout is retried', function () {
    fakeSafepaySession();

    $order = paymentOrder();
    $gateway = app(SafepayGateway::class);

    $gateway->initialize($order);
    $gateway->initialize($order);

    expect($order->payments()->count())->toBe(1);
});

it('fails loudly when safepay returns no tracker', function () {
    Http::fake([
        '*' => Http::response(['data' => []], 201),
    ]);

    app(SafepayGateway::class)->initialize(paymentOrder());
})->throws(RuntimeException::class);

it('reports itself unavailable without credentials', function () {
    configureSafepay(['secret_key' => null]);

    expect(app(SafepayGateway::class)->isEnabled())->toBeFalse();
});

it('refuses to refund a payment that was never paid', function () {
    fakeSafepaySession();

    $payment = paymentRecord(paymentOrder(), [
        'payment_method' => PaymentMethod::SAFEPAY,
        'status' => PaymentStatus::PENDING,
    ]);

    expect(fn () => app(PaymentService::class)
        ->refund($payment))->toThrow(ValidationException::class);
});

it('refunds a paid payment at safepay and records it', function () {
    Http::fake([
        '*/client/transactions/v1/*' => Http::response(['data' => ['state' => 'REFUNDED', 'id' => 'txn_refund']], 201),
    ]);

    $payment = paymentRecord(paymentOrder(), [
        'payment_method' => PaymentMethod::SAFEPAY,
        'status' => PaymentStatus::PAID,
        'transaction_reference' => 'trk_123',
    ]);

    $refunded = app(PaymentService::class)->refund($payment, 500.00, 'damaged');

    expect($refunded->status)->toBe(PaymentStatus::REFUNDED)
        ->and($refunded->transactions()->where('type', 'refund')->count())->toBe(1);
});

/**
 * `verify()` is the path the customer's browser relies on: the return page polls
 * it, so it must read Safepay's *tracker* states. It is also the only signal
 * when the webhook does not reach us — a local run whose endpoint points at
 * production, or a tab that was closed and reopened.
 */
function fakeSafepayTracker(string $state, string $tracker = 'trk_1'): void
{
    Http::fake([
        '*/reporter/api/v2/payments/*' => Http::response([
            'data' => ['token' => $tracker, 'state' => $state],
        ], 200),
    ]);
}

it('treats a TRACKER_ENDED tracker as paid and confirms the order', function () {
    fakeSafepayTracker('TRACKER_ENDED', 'trk_9');

    $order = paymentOrder();
    $payment = paymentRecord($order, [
        'payment_method' => PaymentMethod::SAFEPAY,
        'status' => PaymentStatus::PENDING,
        'transaction_reference' => 'trk_9',
    ]);

    expect(app(SafepayGateway::class)->verify($payment))->toBe(PaymentStatus::PAID)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::PAID)
        ->and($order->fresh()->status)->toBe(OrderStatus::CONFIRMED);
});

it('maps safepay tracker states onto our payment statuses', function (string $state, string $expected) {
    fakeSafepayTracker($state);

    $payment = paymentRecord(paymentOrder(), [
        'payment_method' => PaymentMethod::SAFEPAY,
        'status' => PaymentStatus::PENDING,
        'transaction_reference' => 'trk_1',
    ]);

    expect(app(SafepayGateway::class)->verify($payment))->toBe($expected);
})->with([
    // In flight, disputed or unreadable: never guess these into a failure.
    'started' => ['TRACKER_STARTED', PaymentStatus::PENDING],
    'enrolled' => ['TRACKER_ENROLLED', PaymentStatus::PENDING],
    'disputed' => ['TRACKER_DISPUTED', PaymentStatus::PENDING],
    'not found' => ['TRACKER_NOT_FOUND', PaymentStatus::PENDING],
    'missing' => ['TRACKER_MISSING', PaymentStatus::PENDING],
    // Terminal states.
    'authorized' => ['TRACKER_AUTHORIZED', PaymentStatus::AUTHORIZED],
    'expired' => ['TRACKER_EXPIRED', PaymentStatus::FAILED],
    'cancelled' => ['TRACKER_CANCELLED', PaymentStatus::CANCELLED],
    'voided' => ['TRACKER_VOIDED', PaymentStatus::CANCELLED],
    'reversed' => ['TRACKER_REVERSED', PaymentStatus::CANCELLED],
]);

it('records a refund when safepay reports the tracker refunded', function () {
    fakeSafepayTracker('TRACKER_REFUNDED', 'trk_2');

    $payment = paymentRecord(paymentOrder(), [
        'payment_method' => PaymentMethod::SAFEPAY,
        'status' => PaymentStatus::PAID,
        'transaction_reference' => 'trk_2',
    ]);

    expect(app(SafepayGateway::class)->verify($payment))->toBe(PaymentStatus::REFUNDED);
});
