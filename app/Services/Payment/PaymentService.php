<?php

namespace App\Services\Payment;

use App\Enums\PaymentStatus;
use App\Gateways\PaymentGatewayManager;
use App\Gateways\WebhookRejectedException;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Everything the app needs to do with a payment, expressed in terms of the
 * PaymentGateway interface rather than any one provider.
 */
class PaymentService
{
    public function __construct(
        protected PaymentGatewayManager $gateways,
        protected PaymentReconciler $reconciler,
    ) {}

    /**
     * Payment methods the storefront may offer for an order in this currency.
     *
     * @return array<int, array{method: string, label: string, requires_redirect: bool}>
     */
    public function methods(?string $currency = null): array
    {
        return $this->gateways->available($currency);
    }

    /**
     * A payment by its public identifier. The UUID is unguessable, which is
     * what lets a guest poll the status of their own order without a token.
     */
    public function find(string $uuid): Payment
    {
        return Payment::query()
            ->with(['order', 'transactions'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * Whether a method is known to the app at all (configured or not).
     */
    public function supports(string $method): bool
    {
        return $this->gateways->has($method);
    }

    /**
     * Hand an inbound callback to its gateway. The gateway is resolved even if
     * it has since been disabled, so a payment taken before the switch was
     * flipped can still settle.
     *
     * @throws WebhookRejectedException when verification fails.
     */
    public function handleWebhook(string $method, Request $request): bool
    {
        return $this->gateways->implementation($method)->handleCallback($request);
    }

    /**
     * Ask the gateway for the authoritative status, and apply it.
     */
    public function refresh(Payment $payment): Payment
    {
        $this->gateways->implementation($payment->payment_method)->verify($payment);

        return $payment->fresh(['order', 'transactions']);
    }

    /**
     * Refund in full (`$amount` null) or in part.
     */
    public function refund(Payment $payment, ?float $amount = null, ?string $reason = null): Payment
    {
        if ($payment->status !== PaymentStatus::PAID) {
            throw ValidationException::withMessages([
                'payment' => 'Only a paid payment can be refunded.',
            ]);
        }

        if ($amount !== null && ($amount <= 0 || $amount > (float) $payment->amount)) {
            throw ValidationException::withMessages([
                'amount' => 'The refund amount must be greater than zero and no more than the payment.',
            ]);
        }

        $this->gateways->implementation($payment->payment_method)->refund($payment, $amount, $reason);

        return $payment->fresh(['order', 'transactions']);
    }
}
