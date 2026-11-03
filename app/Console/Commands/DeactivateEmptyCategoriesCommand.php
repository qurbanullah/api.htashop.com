<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Support\Catalog\CategoryCache;
use App\Support\Catalog\CategoryTree;
use Illuminate\Console\Command;

/**
 * Storefront navigation should not lead to empty branches. This command
 * deactivates categories whose entire subtree has no products, so the public
 * tree only advertises branches that can actually be shopped.
 *
 * Roots are excluded by default — a featured root would disappear from the top
 * nav. Use --include-roots to hide empty departments too.
 *
 * Run manually after loading products — never scheduled.
 */
class DeactivateEmptyCategoriesCommand extends Command
{
    protected $signature = 'catalog:deactivate-empty-categories
        {--include-roots : Also deactivate root categories whose subtree has no products}
        {--force : Apply the change instead of listing candidates}';

    protected $description = 'Deactivate categories whose subtree has no products';

    public function handle(): int
    {
        [$categories, $subtreeProducts] = CategoryTree::withSubtreeProductCounts();

        $candidates = $categories->filter(function (Category $category) use ($subtreeProducts): bool {
            if (! $category->is_active) {
                return false;
            }

            if (! $this->option('include-roots') && $category->parent_id === null) {
                return false;
            }

            return ($subtreeProducts[$category->id] ?? 0) === 0;
        });

        if ($candidates->isEmpty()) {
            $this->info('No empty active categories found.');

            return self::SUCCESS;
        }

        $this->warn("{$candidates->count()} empty active categor(ies) found.");

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

            $this->line('Dry run only. Re-run with --force to deactivate these categories.');

            return self::SUCCESS;
        }

        $updated = Category::query()
            ->whereKey($candidates->modelKeys())
            ->update(['is_active' => false]);

        CategoryCache::flush();

        $this->info("Deactivated {$updated} empty categor(ies).");

        return self::SUCCESS;
    }
}
