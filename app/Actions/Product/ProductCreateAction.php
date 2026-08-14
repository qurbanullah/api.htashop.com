<?php

namespace App\Actions\Product;

use App\Actions\Sku\SkuGeneratorAction;
use App\Models\Category;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductCreateAction
{
    public function __construct(
        protected SkuGeneratorAction $skuGenerator,
    ) {
    }

    public function handle(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $attempt = 0;

            while (true) {
                try {
                    return $this->createProduct($data);
                } catch (UniqueConstraintViolationException $e) {
                    // Two concurrent creates collided on the same slug — the conflicting
                    // row is now visible, so re-resolving picks the next suffix. Retry.
                    if (++$attempt >= 3) {
                        throw $e;
                    }
                }
            }
        });
    }

    private function createProduct(array $data): Product
    {
        $organization = Organization::query()->find(data_get($data, 'organization_id'));
        $category = $this->resolveTopLevelCategory(data_get($data, 'category_ids', []));

        $product = Product::create([
            'tenant_id' => data_get($data, 'tenant_id'),
            'organization_id' => data_get($data, 'organization_id'),
            'name' => data_get($data, 'name'),
            'slug' => $this->resolveSlug(data_get($data, 'slug'), data_get($data, 'name'), data_get($data, 'tenant_id')),
            'sku' => $this->skuGenerator->product(
                $organization ?? new Organization(),
                $category,
                (int) data_get($data, 'tenant_id'),
            ),
            'seller_sku' => data_get($data, 'seller_sku'),
            'part_number' => data_get($data, 'part_number'),
            'hs_code' => data_get($data, 'hs_code'),
            'unspsc' => data_get($data, 'unspsc'),
            'ntn' => data_get($data, 'ntn'),
            'barcode' => data_get($data, 'barcode'),
            'model_number' => data_get($data, 'model_number'),
            'status' => data_get($data, 'status', 'draft'),
            'summary' => data_get($data, 'summary'),
            'description' => data_get($data, 'description'),
            'is_active' => data_get($data, 'is_active', true),
            'metadata' => data_get($data, 'metadata'),
        ]);

        $product->categories()->sync(data_get($data, 'category_ids', []));
        $product->features()->sync($this->resolveFeatureIds(
            data_get($data, 'features', []),
            $category?->id,
        ));
        $product->tags()->sync(data_get($data, 'tag_ids', []));
        $product->manufacturers()->sync(data_get($data, 'manufacturer_ids', []));
        $product->brands()->sync(data_get($data, 'brand_ids', []));

        return $product->load([
            'categories',
            'features',
            'tags',
            'manufacturers',
            'brands',
            'variants',
            'attributeValues.definition',
            'attributeValues.option',
            'attributeValues.unit',
            'specificationValues.definition',
            'specificationValues.option',
            'specificationValues.unit',
        ]);
    }

    private function resolveTopLevelCategory(array $categoryIds): ?Category
    {
        $category = Category::query()
            ->whereIn('id', $categoryIds)
            ->orderBy('level')
            ->first();

        while ($category && $category->parent_id) {
            $category = $category->parent;
        }

        return $category;
    }

    /**
     * Resolve feature names into Feature records and return their IDs.
     */
    private function resolveFeatureIds(array $names, ?int $categoryId = null): array
    {
        return collect($names)
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->map(function ($name) use ($categoryId) {
                $feature = Feature::firstOrCreate(
                    ['slug' => Str::slug($name)],
                    ['name' => trim($name), 'is_active' => true],
                );

                if ($categoryId) {
                    $feature->categories()->syncWithoutDetaching([$categoryId]);
                }

                return $feature->id;
            })
            ->unique()
            ->values()
            ->all();
    }

    private function resolveSlug(?string $slug, string $name, int $tenantId): string
    {
        $candidate = Str::slug($slug ?: $name);
        $base = $candidate;
        $suffix = 1;

        // Include trashed products — the unique (tenant_id, slug) index counts them,
        // so a soft-deleted product still reserves its slug.
        while (Product::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
