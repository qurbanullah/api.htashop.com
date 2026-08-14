<?php

namespace App\Actions\Product;

use App\Actions\Revision\RevisionCreateAction;
use App\Models\Category;
use App\Models\Feature;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductUpdateAction
{
    public function __construct(
        protected RevisionCreateAction $revisionCreateAction,
    ) {
    }

    public function handle(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            $this->revisionCreateAction->handle(
                $product,
                $this->buildRevisionPayload($product),
                ['revision_type' => 'update', 'reason' => 'product update'],
            );

            $product->update([
                'tenant_id' => data_get($data, 'tenant_id', $product->tenant_id),
                'organization_id' => data_get($data, 'organization_id', $product->organization_id),
                'name' => data_get($data, 'name', $product->name),
                'slug' => $this->resolveSlugForUpdate($product, $data),
                'seller_sku' => array_key_exists('seller_sku', $data) ? data_get($data, 'seller_sku') : $product->seller_sku,
                'part_number' => array_key_exists('part_number', $data) ? data_get($data, 'part_number') : $product->part_number,
                'hs_code' => array_key_exists('hs_code', $data) ? data_get($data, 'hs_code') : $product->hs_code,
                'unspsc' => array_key_exists('unspsc', $data) ? data_get($data, 'unspsc') : $product->unspsc,
                'ntn' => array_key_exists('ntn', $data) ? data_get($data, 'ntn') : $product->ntn,
                'barcode' => array_key_exists('barcode', $data) ? data_get($data, 'barcode') : $product->barcode,
                'model_number' => array_key_exists('model_number', $data) ? data_get($data, 'model_number') : $product->model_number,
                'status' => data_get($data, 'status', $product->status),
                'summary' => array_key_exists('summary', $data) ? data_get($data, 'summary') : $product->summary,
                'description' => array_key_exists('description', $data) ? data_get($data, 'description') : $product->description,
                'is_active' => data_get($data, 'is_active', $product->is_active),
                'metadata' => array_key_exists('metadata', $data) ? data_get($data, 'metadata') : $product->metadata,
            ]);

            if (array_key_exists('category_ids', $data)) {
                $product->categories()->sync(data_get($data, 'category_ids', []));
            }

            if (array_key_exists('features', $data)) {
                $firstCategoryId = data_get($data, 'category_ids.0') ?? $product->categories->first()?->id;
                $topLevelId = $firstCategoryId ? $this->resolveTopLevelCategoryId((int) $firstCategoryId) : null;
                $product->features()->sync($this->resolveFeatureIds(data_get($data, 'features', []), $topLevelId));
            } elseif (array_key_exists('feature_ids', $data)) {
                $product->features()->sync(data_get($data, 'feature_ids', []));
            }

            if (array_key_exists('tag_ids', $data)) {
                $product->tags()->sync(data_get($data, 'tag_ids', []));
            }

            if (array_key_exists('manufacturer_ids', $data)) {
                $product->manufacturers()->sync(data_get($data, 'manufacturer_ids', []));
            }

            if (array_key_exists('brand_ids', $data)) {
                $product->brands()->sync(data_get($data, 'brand_ids', []));
            }

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
        });
    }

    private function buildRevisionPayload(Product $product): array
    {
        return [
            'tenant_id' => $product->tenant_id,
            'organization_id' => $product->organization_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'status' => $product->status,
            'summary' => $product->summary,
            'description' => $product->description,
            'is_active' => $product->is_active,
            'metadata' => $product->metadata,
            'category_ids' => $product->categories()->pluck('categories.id')->all(),
            'feature_ids' => $product->features()->pluck('features.id')->all(),
            'tag_ids' => $product->tags()->pluck('tags.id')->all(),
        ];
    }

    /**
     * Slug policy:
     *  - Explicit slug edits always win.
     *  - Once published (status = active) the slug is frozen for stable URLs/SEO.
     *  - Draft products keep the slug in sync with the name.
     */
    private function resolveSlugForUpdate(Product $product, array $data): string
    {
        $tenantId = (int) data_get($data, 'tenant_id', $product->tenant_id);

        if (array_key_exists('slug', $data) && ! is_null($data['slug'])) {
            return $this->resolveSlug($product, $data['slug'], data_get($data, 'name', $product->name), $tenantId);
        }

        if ($product->status === 'active' || data_get($data, 'status', $product->status) === 'active') {
            return $product->slug;
        }

        $name = data_get($data, 'name', $product->name);
        if ($name !== $product->name) {
            return $this->resolveSlug($product, null, $name, $tenantId);
        }

        return $product->slug;
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

    /**
     * Walk up the category tree to the top-level parent.
     */
    private function resolveTopLevelCategoryId(int $categoryId): int
    {
        $category = Category::query()->find($categoryId);

        while ($category && $category->parent_id) {
            $category = $category->parent;
        }

        return $category?->id ?? $categoryId;
    }

    private function resolveSlug(Product $product, ?string $slug, string $name, int $tenantId): string
    {
        $candidate = Str::slug($slug ?: $name);
        $base = $candidate;
        $suffix = 1;

        // Include trashed products — the unique (tenant_id, slug) index counts them.
        while (Product::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('slug', $candidate)
            ->where('id', '!=', $product->id)
            ->exists()) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
