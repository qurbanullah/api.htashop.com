<?php

namespace App\Http\Controllers\V1\Payment;

use App\Gateways\WebhookRejectedException;
use App\Http\Controllers\Controller;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $payments,
    ) {}

    /**
     * Payment methods the storefront may offer. Pass `?currency=PKR` to filter
     * out gateways that cannot settle that currency.
     */
    public function methods(Request $request): JsonResponse
    {
        $currency = $request->query('currency');

        // Fall back to the storefront's trading currency so the caller can ask
        // for the list without knowing which one that is.
        if (! is_string($currency) || $currency === '') {
            $currency = (string) config('payment.currency');
        }

        return ApiResponse::success(
            ['methods' => $this->payments->methods($currency)],
            'Payment methods retrieved successfully',
        );
    }

    /**
     * Local status for a payment. `?refresh=1` asks the gateway for the
     * authoritative status first — slower, but it settles an order whose
     * webhook was never delivered.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $payment = $this->payments->find($uuid);

        if ($request->boolean('refresh')) {
            $payment = $this->payments->refresh($payment);
        }

        return ApiResponse::success($this->present($payment), 'Payment retrieved successfully');
    }

    /**
     * Gateway callback.
     *
     * Unauthenticated by necessity, so trust comes entirely from the signature.
     * A verified request always gets a 2xx: a non-2xx makes the gateway retry,
     * and retrying will not turn an event we ignore into one we act on.
     */
    public function webhook(Request $request, string $method): JsonResponse
    {
        if (! $this->payments->supports($method)) {
            return ApiResponse::error('Unknown payment method.', null, 404);
        }

        try {
            $handled = $this->payments->handleWebhook($method, $request);
        } catch (WebhookRejectedException $e) {
            // Already logged with detail by the gateway.
            return ApiResponse::error('Webhook rejected.', ['reason' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            Log::error('Payment webhook failed.', [
                'method' => $method,
                'exception' => $e->getMessage(),
            ]);

            // 500 so the gateway retries: we are the ones who broke.
            return ApiResponse::error('Webhook processing failed.', null, 500);
        }

        return ApiResponse::success(
            ['handled' => $handled],
            $handled ? 'Webhook processed' : 'Webhook ignored',
        );
    }

    /**
     * Refund in full (no body) or in part (`amount`). Admin-only.
     */
    public function refund(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $payment = $this->payments->refund(
            $this->payments->find($uuid),
            isset($data['amount']) ? (float) $data['amount'] : null,
            $data['reason'] ?? null,
        );

        return ApiResponse::success($this->present($payment), 'Payment refunded successfully');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Payment $payment): array
    {
        return [
            'uuid' => $payment->uuid,
            'order_uuid' => $payment->order?->uuid,
            'order_number' => $payment->order?->order_number,
            'payment_method' => $payment->payment_method,
            'status' => $payment->status,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'transaction_reference' => $payment->transaction_reference,
            'transactions' => $payment->transactions->map(fn ($transaction) => [
                'uuid' => $transaction->uuid,
                'type' => $transaction->type,
                'status' => $transaction->status,
                'amount' => (float) $transaction->amount,
                'currency' => $transaction->currency,
                'created_at' => $transaction->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
