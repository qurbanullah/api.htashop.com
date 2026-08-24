<?php

namespace App\Gateways;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Contract every payment gateway implements. The checkout flow only ever
 * talks to this interface, so JazzCash / EasyPaisa / UPaisa / Safepay can be
 * added without touching order creation.
 */
interface PaymentGateway
{
    /**
     * Create a payment record for the order and, if the gateway needs it,
     * return a redirect/HTML result (COD returns a no-op result).
     */
    public function initialize(Order $order): PaymentInitResult;

    /**
     * Verify a payment's current status (used by confirm/status endpoints and
     * scheduled reconciliation).
     */
    public function verify(Payment $payment): string;

    /**
     * Handle an inbound webhook/callback from the gateway.
     * Returns true when the request was accepted and processed.
     */
    public function handleCallback(Request $request): bool;
}
