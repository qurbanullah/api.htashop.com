<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Support\Catalog\CategoryCache;
use App\Support\Catalog\CategoryTree;
use Illuminate\Console\Command;

/**
 * Counterpart to catalog:deactivate-empty-categories.
 *
 * Activate every category that has products somewhere in its subtree — the
 * stocked leaves plus the ancestors needed to reach them. Empty siblings stay
 * inactive, so the storefront only grows the branches that carry SKUs.
 *
 * Typical use: deactivate empty branches after a catalog purge, load products,
 * then run this command to publish the branches that now have stock.
 *
 * Run manually — never scheduled.
 */
class ActivateStockedCategoriesCommand extends Command
{
    protected $signature = 'catalog:activate-stocked-categories
        {--force : Apply the change instead of listing candidates}';

    protected $description = 'Activate categories whose subtree contains at least one product';

    public function handle(): int
    {
        [$categories, $subtreeProducts] = CategoryTree::withSubtreeProductCounts();

        $candidates = $categories->filter(
            fn (Category $category): bool => ! $category->is_active
                && ($subtreeProducts[$category->id] ?? 0) > 0
        );

        if ($candidates->isEmpty()) {
            $this->info('No inactive stocked categories found.');

            return self::SUCCESS;
        }

        $this->warn("{$candidates->count()} inactive categor(ies) have products in their subtree.");

        if (! $this->option('force')) {
            $this->table(
                ['Slug', 'Name', 'Level', 'Products in subtree'],
                $candidates->map(fn (Category $category) => [
                    $category->slug,
                    $category->name,
                    $category->level,
                    $subtreeProducts[$category->id] ?? 0,
                ])->all(),
            );

            $this->line('Dry run only. Re-run with --force to activate these categories.');

            return self::SUCCESS;
        }

        $updated = Category::query()
            ->whereKey($candidates->modelKeys())
            ->update(['is_active' => true]);

        CategoryCache::flush();

        $this->info("Activated {$updated} stocked categor(ies).");

        return self::SUCCESS;
    }
}
