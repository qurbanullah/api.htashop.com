<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Gateways\CodGateway;
use App\Models\Order;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        protected CodGateway $codGateway,
    ) {
    }

    public function read(array $filters = []): LengthAwarePaginator
    {
        $user = auth()->user();

        $query = Order::query()
            ->with(['items', 'payments'])
            ->when(data_get($filters, 'search'), function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%");
                });
            })
            ->when(data_get($filters, 'status'), fn ($query, $status) => $query->where('status', $status))
            ->when(data_get($filters, 'payment_status'), function ($query, $paymentStatus) {
                $query->whereHas('payments', fn ($payment) => $payment->where('status', $paymentStatus));
            });

        // Non-admin users are scoped to their active membership tenant.
        if (! $user?->hasRole(['super-admin', 'admin'])) {
            $tenantId = $user?->memberships()->where('is_active', true)->first()?->tenant_id;
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
        }

        return $query->orderByDesc('placed_at')
            ->orderByDesc('created_at')
            ->paginate((int) data_get($filters, 'per_page', 20));
    }

    public function show(string $uuid): Order
    {
        return Order::query()
            ->with(['items', 'payments.transactions'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * Buyer-scoped order history for the authenticated customer.
     */
    public function myOrders(array $filters = []): LengthAwarePaginator
    {
        return Order::query()
            ->with(['items', 'payments'])
            ->where('user_id', auth()->id())
            ->when(data_get($filters, 'search'), function ($query, $search) {
                $query->where('order_number', 'like', "%{$search}%");
            })
            ->when(data_get($filters, 'status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('placed_at')
            ->orderByDesc('created_at')
            ->paginate((int) data_get($filters, 'per_page', 10));
    }

    /**
     * Buyer-scoped single order — 404 unless it belongs to the authenticated user.
     */
    public function myOrder(string $uuid): Order
    {
        return Order::query()
            ->with(['items', 'payments.transactions'])
            ->where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->firstOrFail();
    }

    /**
     * Transition an order to a new status. Delivering a COD order captures the
     * payment (creates the ledger transaction and marks the payment paid).
     */
    public function updateStatus(Order $order, string $status): Order
    {
        if (! in_array($status, OrderStatus::ALL, true)) {
            throw ValidationException::withMessages(['status' => 'Invalid order status.']);
        }

        $order->update([
            'status' => $status,
            'confirmed_at' => $status === OrderStatus::CONFIRMED && ! $order->confirmed_at
                ? now()
                : $order->confirmed_at,
        ]);

        // COD payment is collected at delivery.
        if ($status === OrderStatus::DELIVERED) {
            $payment = $order->payments()->where('payment_method', 'cod')->first();
            if ($payment && $payment->status !== PaymentStatus::PAID) {
                $this->codGateway->capture($payment);
            }
        }

        return $order->fresh(['items', 'payments.transactions']);
    }
}
