<?php

namespace App\Gateways;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TransactionType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;

/**
 * Cash on Delivery — payment is collected by the courier at delivery.
 */
class CodGateway implements PaymentGateway
{
    public function initialize(Order $order): PaymentInitResult
    {
        Payment::create([
            'order_id' => $order->id,
            'payment_method' => PaymentMethod::COD,
            'status' => PaymentStatus::PENDING,
            'amount' => $order->total_amount,
            'currency' => $order->currency,
        ]);

        return new PaymentInitResult(requiresRedirect: false);
    }

    public function verify(Payment $payment): string
    {
        // COD stays pending until the order is delivered; marking the order
        // delivered captures the money (see OrderService::markDelivered).
        return $payment->status;
    }

    public function handleCallback(Request $request): bool
    {
        // COD has no gateway callbacks.
        return false;
    }

    /**
     * Record the money movement when COD is collected at delivery.
     */
    public static function capture(Payment $payment): void
    {
        Transaction::create([
            'payment_id' => $payment->id,
            'type' => TransactionType::CAPTURE,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => 'succeeded',
        ]);

        $payment->update(['status' => PaymentStatus::PAID]);
    }
}
