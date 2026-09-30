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
     * The PaymentMethod value this gateway handles (e.g. `cod`, `safepay`).
     */
    public function method(): string;

    /**
     * Human-readable name for the storefront's payment options.
     */
    public function label(): string;

    /**
     * Whether the gateway can currently take payments: switched on *and*
     * holding credentials. Checkout only offers enabled gateways; COD is
     * always enabled.
     */
    public function isEnabled(): bool;

    /**
     * Whether the gateway can settle in the given currency. Used to keep a
     * PKR-only gateway off a USD order; a null currency means "any".
     */
    public function supportsCurrency(?string $currency): bool;

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
     *
     * Returns true when a signature-valid request was accepted and applied,
     * false when it verified but was not an event we act on (so the gateway
     * still gets its 2xx). Throws WebhookRejectedException when the request
     * cannot be trusted.
     */
    public function handleCallback(Request $request): bool;

    /**
     * Return money to the customer. `$amount` is null for a full refund.
     * Gateways that settle out of band (COD) simply record the movement.
     */
    public function refund(Payment $payment, ?float $amount = null, ?string $reason = null): void;
}
