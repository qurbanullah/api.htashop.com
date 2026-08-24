<?php

namespace App\Http\Controllers\V1\Checkout;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Order\OrderResource;
use App\Http\Responses\V1\ApiResponse;
use App\Services\Checkout\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService,
    ) {
    }

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
            'shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->checkoutService->checkout($request, $data);

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
}
