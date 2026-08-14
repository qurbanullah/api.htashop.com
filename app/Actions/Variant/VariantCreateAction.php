<?php

namespace App\Actions\Variant;

use App\Actions\Sku\SkuGeneratorAction;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VariantCreateAction
{
    public function __construct(
        protected SkuGeneratorAction $skuGenerator,
    ) {
    }

    public function handle(array $data): Variant
    {
        return DB::transaction(function () use ($data): Variant {
            $product = Product::query()->findOrFail(data_get($data, 'product_id'));

            $variant = Variant::create([
                'tenant_id' => $product->tenant_id,
                'product_id' => data_get($data, 'product_id'),
                'name' => data_get($data, 'name'),
                'slug' => $this->resolveSlug(data_get($data, 'slug'), data_get($data, 'name'), data_get($data, 'product_id')),
                'sku' => $this->skuGenerator->variant(
                    $product,
                    data_get($data, 'configuration', []),
                    data_get($data, 'metadata.config_details', []),
                ),
                'seller_sku' => data_get($data, 'seller_sku'),
                'status' => data_get($data, 'status', 'draft'),
                'summary' => data_get($data, 'summary'),
                'description' => data_get($data, 'description'),
                'configuration' => data_get($data, 'configuration'),
                'is_default' => data_get($data, 'is_default', false),
                'is_active' => data_get($data, 'is_active', true),
                'metadata' => data_get($data, 'metadata'),
            ]);

            $variant->categories()->sync(data_get($data, 'category_ids', []));
            $variant->features()->sync(data_get($data, 'feature_ids', []));
            $variant->tags()->sync(data_get($data, 'tag_ids', []));

            return $variant->load([
                'product',
                'categories',
                'features',
                'tags',
                'attributeValues.definition',
                'attributeValues.option',
                'attributeValues.unit',
                'specificationValues.definition',
                'specificationValues.option',
                'specificationValues.unit',
            ]);
        });
    }

    private function resolveSlug(?string $slug, string $name, int $productId): string
    {
        $candidate = Str::slug($slug ?: $name);
        $base = $candidate;
        $suffix = 1;

        while (Variant::query()->where('product_id', $productId)->where('slug', $candidate)->exists()) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
