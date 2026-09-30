<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Gateways\CodGateway;
use App\Models\Payment;
use App\Services\Payment\PaymentReconciler;

beforeEach(function () {
    $this->reconciler = app(PaymentReconciler::class);
});

it('records a charge and confirms the order on success', function () {
    $order = paymentOrder();
    $payment = paymentRecord($order);

    $result = $this->reconciler->sync($payment, PaymentStatus::PAID, [], 'txn_1');

    expect($result->status)->toBe(PaymentStatus::PAID)
        ->and($result->transaction_reference)->toBe('txn_1')
        ->and($order->fresh()->status)->toBe(OrderStatus::CONFIRMED);

    $charge = Payment::query()->find($payment->id)->transactions()->sole();

    expect($charge->type)->toBe('charge')
        ->and($charge->status)->toBe('succeeded');
});

it('never double-posts the ledger for a repeated success', function () {
    $payment = paymentRecord(paymentOrder());

    $this->reconciler->sync($payment, PaymentStatus::PAID);
    $this->reconciler->sync($payment, PaymentStatus::PAID);
    $this->reconciler->sync($payment->fresh(), PaymentStatus::PAID);

    expect($payment->transactions()->count())->toBe(1);
});

it('refunds a paid payment and refunds the order', function () {
    $order = paymentOrder();
    $payment = paymentRecord($order);

    $this->reconciler->sync($payment, PaymentStatus::PAID);
    $refunded = $this->reconciler->sync($payment, PaymentStatus::REFUNDED, [], null, 500.00);

    expect($refunded->status)->toBe(PaymentStatus::REFUNDED)
        ->and($order->fresh()->status)->toBe(OrderStatus::REFUNDED);

    $refund = $payment->transactions()->where('type', 'refund')->sole();

    expect((float) $refund->amount)->toBe(500.00);
});

it('ignores a failure that arrives after the payment succeeded', function () {
    $payment = paymentRecord(paymentOrder());

    $this->reconciler->sync($payment, PaymentStatus::PAID);
    $result = $this->reconciler->sync($payment, PaymentStatus::FAILED);

    expect($result->status)->toBe(PaymentStatus::PAID)
        ->and($payment->transactions()->where('status', 'failed')->count())->toBe(0);
});

it('records a failed attempt without touching the order', function () {
    $order = paymentOrder();

    $result = $this->reconciler->sync(paymentRecord($order), PaymentStatus::FAILED);

    expect($result->status)->toBe(PaymentStatus::FAILED)
        ->and($order->fresh()->status)->toBe(OrderStatus::PENDING);
});

it('rejects an unknown status', function () {
    $this->reconciler->sync(paymentRecord(paymentOrder()), 'something-else');
})->throws(InvalidArgumentException::class);

it('captures a delivered COD order through the reconciler', function () {
    $order = paymentOrder();
    $payment = paymentRecord($order, ['payment_method' => PaymentMethod::COD]);

    app(CodGateway::class)->capture($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::PAID)
        ->and($order->fresh()->status)->toBe(OrderStatus::CONFIRMED)
        ->and($payment->transactions()->count())->toBe(1);
});
