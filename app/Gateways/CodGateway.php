<?php

namespace App\Gateways;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\PaymentReconciler;
use Illuminate\Http\Request;

/**
 * Cash on Delivery — payment is collected by the courier at delivery.
 */
class CodGateway implements PaymentGateway
{
    public function __construct(
        protected PaymentReconciler $reconciler,
    ) {}

    public function method(): string
    {
        return PaymentMethod::COD;
    }

    public function label(): string
    {
        return PaymentMethod::label(PaymentMethod::COD);
    }

    /**
     * COD has no credentials to wait for, and is the fallback when every
     * hosted gateway is disabled.
     */
    public function isEnabled(): bool
    {
        return true;
    }

    public function supportsCurrency(?string $currency): bool
    {
        // Cash settles in whatever the order is priced in.
        return true;
    }

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
     * Refunds on a COD order happen out of band (cash back at the door); there
     * is nothing to call, so we only record the movement.
     */
    public function refund(Payment $payment, ?float $amount = null, ?string $reason = null): void
    {
        $this->reconciler->sync(
            $payment,
            PaymentStatus::REFUNDED,
            ['source' => 'cod', 'reason' => $reason],
            null,
            $amount,
        );
    }

    /**
     * Record the money movement when COD is collected at delivery.
     */
    public function capture(Payment $payment): void
    {
        $this->reconciler->sync(
            $payment,
            PaymentStatus::PAID,
            ['source' => 'cod_collected'],
        );
    }
}
