<?php

namespace App\Actions\Catalog;

use App\Models\OrderItem;

class CatalogPopularityReadAction
{
    /**
     * Build the product popularity ranking map (product_id => sold quantity),
     * ordered best-first. This Action performs only the database read; caching
     * is the responsibility of the Service layer.
     *
     * @return array<int, int|float>
     */
    public function handle(string $sort): array
    {
        $query = OrderItem::query()
            ->whereNotNull('order_items.product_id')
            ->whereHas('order', fn ($orderQuery) => $orderQuery->whereNotIn('status', ['cancelled', 'refunded']));

        if ($sort === 'trending') {
            $query->where('order_items.created_at', '>=', now()->subDays(30));
        }

        return $query
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) as score')
            ->groupBy('order_items.product_id')
            ->orderByDesc('score')
            ->get()
            ->pluck('score', 'product_id')
            ->all();
    }
}
