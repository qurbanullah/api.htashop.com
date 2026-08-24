<?php

namespace App\Http\Controllers\V1\Order;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Order\OrderIndexRequest;
use App\Http\Resources\V1\Order\OrderResource;
use App\Http\Responses\V1\ApiResponse;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
    ) {
    }

    public function index(OrderIndexRequest $request): JsonResponse
    {
        $orders = $this->orderService->read($request->validated());

        return ApiResponse::success(
            [
                'data' => OrderResource::collection($orders->items())->resolve(),
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'from' => $orders->firstItem(),
                    'to' => $orders->lastItem(),
                ],
            ],
            'Orders retrieved successfully',
        );
    }

    public function show(string $uuid): JsonResponse
    {
        return ApiResponse::success(
            new OrderResource($this->orderService->show($uuid)),
            'Order retrieved successfully',
        );
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:pending,confirmed,processing,shipped,delivered,cancelled,refunded'],
        ]);

        $order = $this->orderService->show($uuid);

        return ApiResponse::success(
            new OrderResource($this->orderService->updateStatus($order, $data['status'])),
            'Order status updated successfully',
        );
    }

    /**
     * Buyer account — order history scoped to the authenticated customer.
     */
    public function myOrders(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:' . implode(',', OrderStatus::ALL)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $orders = $this->orderService->myOrders($filters);

        return ApiResponse::success(
            [
                'data' => OrderResource::collection($orders->items())->resolve(),
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'from' => $orders->firstItem(),
                    'to' => $orders->lastItem(),
                ],
            ],
            'Orders retrieved successfully',
        );
    }

    /**
     * Buyer account — single order scoped to the authenticated customer.
     */
    public function myOrder(string $uuid): JsonResponse
    {
        return ApiResponse::success(
            new OrderResource($this->orderService->myOrder($uuid)),
            'Order retrieved successfully',
        );
    }
}
