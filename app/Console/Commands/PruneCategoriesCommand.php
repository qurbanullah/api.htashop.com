<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Support\Catalog\CategoryCache;
use Database\Seeders\CategorySeeder;
use Illuminate\Console\Command;

/**
 * CategorySeeder only upserts: removing an entry from the seed file leaves the
 * old row live in the database. This command reports those rows and, with
 * --force, soft-deletes the ones that are safe to remove.
 *
 * A category is only removed when it has no products and no subcategories; the
 * loop runs bottom-up so a stale subtree collapses in a single pass.
 *
 * Run manually after editing the taxonomy — never scheduled.
 */
class PruneCategoriesCommand extends Command
{
    protected $signature = 'catalog:prune-categories
        {--force : Soft-delete the removable categories instead of listing them}';

    protected $description = 'List or remove categories that no longer exist in CategorySeeder';

    public function handle(): int
    {
        $seededSlugs = CategorySeeder::seededSlugs();

        if ($seededSlugs === []) {
            $this->error('CategorySeeder defines no categories; refusing to prune.');

            return self::FAILURE;
        }

        $stale = Category::query()
            ->whereNotIn('slug', $seededSlugs)
            ->orderByDesc('level')
            ->orderBy('name')
            ->get();

        if ($stale->isEmpty()) {
            $this->info('No stale categories found.');

            return self::SUCCESS;
        }

        $this->warn("{$stale->count()} category(ies) are not defined in CategorySeeder.");

        if (! $this->option('force')) {
            $this->table(
                ['Slug', 'Name', 'Parent ID', 'Status'],
                $stale->map(fn (Category $category) => [
                    $category->slug,
                    $category->name,
                    $category->parent_id ?? '—',
                    $this->blockReason($category) ?? 'Removable',
                ])->all(),
            );

            $this->line('Dry run only. Re-run with --force to soft-delete the removable categories.');

            return self::SUCCESS;
        }

        $deleted = 0;

        do {
            $progress = false;

            foreach ($stale as $category) {
                if ($category->trashed() || $this->blockReason($category) !== null) {
                    continue;
                }

                $category->delete();
                $deleted++;
                $progress = true;
            }
        } while ($progress);

        CategoryCache::flush();

        $remaining = $stale->filter(fn (Category $category) => ! $category->trashed());

        $this->info("Soft-deleted {$deleted} stale category(ies).");

        if ($remaining->isNotEmpty()) {
            $this->warn("{$remaining->count()} stale category(ies) were kept because they are still in use:");

            $this->table(
                ['Slug', 'Reason'],
                $remaining->map(fn (Category $category) => [
                    $category->slug,
                    $this->blockReason($category) ?? 'Unknown',
                ])->all(),
            );
        }

        return self::SUCCESS;
    }

    private function blockReason(Category $category): ?string
    {
        if ($category->children()->exists()) {
            return 'Has subcategories';
        }

        if ($category->products()->exists()) {
            return 'Has products assigned';
        }

        return null;
    }
}
