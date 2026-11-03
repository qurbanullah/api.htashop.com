<?php

namespace App\Support\Catalog;

use App\Models\Category;
use Illuminate\Support\Collection;

/**
 * Helpers for catalog visibility checks.
 *
 * A category is only shoppable when at least one product is assigned somewhere
 * in its subtree. A root with 25 empty children is still an empty branch, which
 * is why visibility cannot be decided from `children()->exists()` alone.
 */
class CategoryTree
{
    /**
     * Load every category with its direct product count plus the product count
     * of its whole subtree.
     *
     * @return array{0: Collection<int, Category>, 1: array<int, int>}
     */
    public static function withSubtreeProductCounts(): array
    {
        $categories = Category::query()
            ->withCount('products')
            ->get(['id', 'name', 'slug', 'parent_id', 'level', 'is_active']);

        /** @var array<int, Category> $byId */
        $byId = $categories->keyBy('id')->all();

        /** @var array<int, array<int, Category>> $children */
        $children = [];

        foreach ($categories as $category) {
            if ($category->parent_id === null) {
                continue;
            }

            $children[$category->parent_id][] = $category;
        }

        /** @var array<int, int> $subtreeProducts */
        $subtreeProducts = [];

        /** @var array<int, bool> $visiting */
        $visiting = [];

        $sum = function (int $id) use (&$sum, &$subtreeProducts, &$visiting, $byId, $children): int {
            if (isset($subtreeProducts[$id])) {
                return $subtreeProducts[$id];
            }

            // Defensive: API validation prevents cycles, but a direct DB edit
            // must not send this traversal into infinite recursion.
            if (isset($visiting[$id])) {
                return 0;
            }
            $visiting[$id] = true;

            $total = (int) ($byId[$id]->products_count ?? 0);

            foreach ($children[$id] ?? [] as $child) {
                $total += $sum($child->id);
            }

            unset($visiting[$id]);

            return $subtreeProducts[$id] = $total;
        };

        foreach ($categories as $category) {
            if ($category->parent_id === null) {
                $sum($category->id);
            }
        }

        return [$categories, $subtreeProducts];
    }
}
