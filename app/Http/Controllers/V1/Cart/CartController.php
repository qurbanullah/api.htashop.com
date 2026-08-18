<?php

namespace App\Http\Controllers\V1\Cart;

use App\Http\Controllers\Controller;
use App\Http\Responses\V1\ApiResponse;
use App\Services\Cart\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->cartService->read($request),
            'Cart retrieved successfully',
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        return ApiResponse::success(
            $this->cartService->addItem($request, $data),
            'Item added to cart',
            201,
        );
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        return ApiResponse::success(
            $this->cartService->updateItem($request, $uuid, (int) $data['quantity']),
            'Cart updated successfully',
        );
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        return ApiResponse::success(
            $this->cartService->removeItem($request, $uuid),
            'Item removed from cart',
        );
    }

    public function clear(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->cartService->clear($request),
            'Cart cleared',
        );
    }
}
