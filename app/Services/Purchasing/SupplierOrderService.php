<?php

namespace App\Services\Purchasing;

use App\Enums\OrderStatus;
use App\Enums\Sourcing;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

/**
 * Rolls open import-on-demand orders up into a supplier purchase list.
 *
 * Only line items whose product is sourced on demand (i.e. not `in_stock`) are
 * included — in-stock goods are fulfilled from held inventory, not ordered from
 * the supplier. Lines are aggregated by variant (falling back to product) so a
 * single row reads "buy N units of SKU X".
 */
class SupplierOrderService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{lines: array<int, array<string, mixed>>, totals: array<string, int>}
     */
    public function aggregateOnDemand(array $filters = []): array
    {
        $statuses = data_get($filters, 'statuses', $this->openStatuses());

        $items = OrderItem::query()
            ->with(['product', 'variant'])
            ->whereHas('order', fn ($query) => $query->whereIn('status', $statuses))
            ->whereHas('product', fn ($query) => $query->where('sourcing', '!=', Sourcing::IN_STOCK))
            ->get();

        $lines = $items
            ->groupBy(fn (OrderItem $item) => $item->variant_id ? "v{$item->variant_id}" : "p{$item->product_id}")
            ->map(fn (Collection $group) => $this->summarize($group))
            ->sortBy('sku')
            ->values()
            ->all();

        return [
            'lines' => $lines,
            'totals' => [
                'sku_count' => count($lines),
                'units' => (int) $items->sum('quantity'),
                'orders' => $items->pluck('order_id')->unique()->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int, OrderItem>  $group
     * @return array<string, mixed>
     */
    private function summarize(Collection $group): array
    {
        $first = $group->first();
        $product = $first->product;
        $variant = $first->variant;

        return [
            'product_id' => $product?->id,
            'variant_id' => $variant?->id,
            'sku' => $variant?->sku ?? $product?->sku,
            'name' => $product?->name,
            'variant_name' => $variant?->name,
            'supplier_reference' => $product?->supplier_reference,
            'sourcing_url' => $product?->sourcing_url,
            'hs_code' => $product?->hs_code,
            'origin_country' => $product?->origin_country,
            'total_quantity' => (int) $group->sum('quantity'),
            'order_count' => $group->pluck('order_id')->unique()->count(),
        ];
    }

    /**
     * Order statuses that still need (or are undergoing) supplier sourcing.
     * Terminal and domestic-delivery states are excluded.
     *
     * @return array<int, string>
     */
    private function openStatuses(): array
    {
        return [
            OrderStatus::PENDING,
            OrderStatus::CONFIRMED,
            OrderStatus::AWAITING_SOURCING,
            OrderStatus::IN_TRANSIT,
            OrderStatus::IN_CUSTOMS,
        ];
    }
}
