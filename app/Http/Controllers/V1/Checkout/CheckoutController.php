<?php

namespace App\Http\Controllers\V1\Checkout;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Order\OrderResource;
use App\Http\Responses\V1\ApiResponse;
use App\Services\Checkout\CheckoutService;
use App\Services\Coupon\CouponException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'checkout_token' => ['nullable', 'string', 'max:64'],
            'payment_method' => ['nullable', 'string', 'in:cod,jazzcash,easypaisa,upaisa,safepay'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'shipping_address' => ['nullable'],
            'shipping_address.contact_name' => ['sometimes', 'string', 'max:255'],
            'shipping_address.phone' => ['sometimes', 'string', 'max:50'],
            'shipping_address.address_line_1' => ['sometimes', 'string', 'max:255'],
            'shipping_address.address_line_2' => ['sometimes', 'string', 'max:255'],
            'shipping_address.city' => ['sometimes', 'string', 'max:100'],
            'shipping_address.state' => ['sometimes', 'string', 'max:100'],
            'shipping_address.postal_code' => ['sometimes', 'string', 'max:20'],
            'shipping_address.country_id' => ['sometimes', 'integer', 'exists:countries,id'],
            'billing_address' => ['nullable'],
            'billing_address.contact_name' => ['sometimes', 'string', 'max:255'],
            'billing_address.phone' => ['sometimes', 'string', 'max:50'],
            'billing_address.address_line_1' => ['sometimes', 'string', 'max:255'],
            'billing_address.address_line_2' => ['sometimes', 'string', 'max:255'],
            'billing_address.city' => ['sometimes', 'string', 'max:100'],
            'billing_address.state' => ['sometimes', 'string', 'max:100'],
            'billing_address.postal_code' => ['sometimes', 'string', 'max:20'],
            'billing_address.country_id' => ['sometimes', 'integer', 'exists:countries,id'],
            // Delivery, tax and any discount are computed server-side from
            // `config/shipping.php` and the coupon table. Amounts in the payload
            // are deliberately not validated or read, so a crafted request
            // cannot discount itself.
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $result = $this->checkoutService->checkout($request, $data);
        } catch (CouponException $e) {
            return $this->couponError($e);
        }

        return ApiResponse::success(
            [
                'order' => new OrderResource($result['order']),
                'payment' => $result['payment'] ? [
                    'uuid' => $result['payment']->uuid,
                    'payment_method' => $result['payment']->payment_method,
                    'status' => $result['payment']->status,
                    'amount' => (float) $result['payment']->amount,
                    'currency' => $result['payment']->currency,
                ] : null,
                'requires_redirect' => $result['requires_redirect'],
                'redirect_url' => $result['redirect_url'],
                // Set when the order was placed but the gateway could not be
                // reached; the storefront should offer a retry rather than
                // claiming the payment started.
                'payment_error' => $result['payment_error'] ?? null,
            ],
            'Order placed successfully',
            201,
        );
    }

    public function show(string $uuid): JsonResponse
    {
        return ApiResponse::success(
            new OrderResource($this->checkoutService->read($uuid)),
            'Order retrieved successfully',
        );
    }

    /**
     * Price the current cart without placing an order.
     *
     * The storefront calls this as the basket, address and coupon code change,
     * so the delivery charge and discount are visible before the customer
     * commits — and, crucially, are the same numbers the order will use.
     */
    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $totals = $this->checkoutService->quote($request, data_get($data, 'coupon_code'));
        } catch (CouponException $e) {
            return $this->couponError($e);
        }

        return ApiResponse::success($totals->toArray(), 'Quote retrieved successfully');
    }

    /**
     * An unusable coupon is a 422 with a translatable reason token, not a 500:
     * a mistyped code is an ordinary thing for a customer to do.
     */
    private function couponError(CouponException $e): JsonResponse
    {
        return ApiResponse::error($e->reason, [
            'errors' => ['coupon_code' => [$e->reason]],
            'reason' => $e->reason,
        ], 422);
    }
}
