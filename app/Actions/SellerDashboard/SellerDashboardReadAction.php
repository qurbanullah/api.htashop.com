<?php

namespace App\Actions\SellerDashboard;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;

class SellerDashboardReadAction
{
    public function handle(?int $tenantId): array
    {
        $productQuery = Product::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));
        $variantQuery = Variant::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));
        $inventoryQuery = Inventory::query()->when($tenantId, fn ($q) => $q->where('inventories.tenant_id', $tenantId));
        $orderQuery = Order::query()->when($tenantId, fn ($q) => $q->where('orders.tenant_id', $tenantId));

        $orders = (clone $orderQuery)->count();
        $revenue = (float) (clone $orderQuery)->sum('total_amount');

        return [
            'counts' => [
                'products' => (clone $productQuery)->count(),
                'active_products' => (clone $productQuery)->where('status', 'active')->count(),
                'draft_products' => (clone $productQuery)->where('status', 'draft')->count(),
                'variants' => (clone $variantQuery)->count(),
                'inventory_items' => (clone $inventoryQuery)->count(),
                'low_stock_items' => (clone $inventoryQuery)
                    ->whereNotNull('low_stock_threshold')
                    ->whereRaw('(quantity - reserved) <= low_stock_threshold')
                    ->count(),
                'total_stock' => (float) (clone $inventoryQuery)->sum('quantity'),
                'orders' => $orders,
                'revenue' => $revenue,
                'average_order_value' => $orders > 0 ? round($revenue / $orders, 2) : 0.0,
                'orders_to_fulfill' => (clone $orderQuery)
                    ->whereIn('status', ['pending', 'confirmed', 'processing'])
                    ->count(),
            ],
            'orders_by_status' => $this->ordersByStatus(clone $orderQuery),
            'orders_by_day' => $this->ordersByDay(clone $orderQuery),
            'revenue_by_day' => $this->revenueByDay(clone $orderQuery),
            'recent_orders' => $this->recentOrders(clone $orderQuery),
        ];
    }

    private function ordersByStatus($orderQuery): array
    {
        return $orderQuery
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    private function ordersByDay($orderQuery): array
    {
        return $orderQuery
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subDays(30)->startOfDay())
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    private function revenueByDay($orderQuery): array
    {
        return $orderQuery
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total_amount) as revenue'))
            ->where('created_at', '>=', now()->subDays(30)->startOfDay())
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'revenue' => (float) $row->revenue,
            ])
            ->values()
            ->all();
    }

    private function recentOrders($orderQuery): array
    {
        return $orderQuery
            ->latest('created_at')
            ->limit(5)
            ->get(['id', 'uuid', 'order_number', 'customer_name', 'customer_email', 'status', 'total_amount', 'currency', 'created_at'])
            ->map(fn ($order) => [
                'id' => $order->id,
                'uuid' => $order->uuid,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'status' => $order->status,
                'total_amount' => (float) $order->total_amount,
                'currency' => $order->currency,
                'created_at' => $order->created_at,
            ])
            ->values()
            ->all();
    }
}
