<?php

namespace App\Actions\Variant;

use App\Actions\Revision\RevisionCreateAction;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VariantUpdateAction
{
    public function __construct(
        protected RevisionCreateAction $revisionCreateAction,
    ) {
    }

    public function handle(Variant $variant, array $data): Variant
    {
        return DB::transaction(function () use ($variant, $data): Variant {
            $this->revisionCreateAction->handle(
                $variant,
                $this->buildRevisionPayload($variant),
                ['revision_type' => 'update', 'reason' => 'variant update'],
            );
            $productId = (int) data_get($data, 'product_id', $variant->product_id);
            $product = Product::query()->findOrFail($productId);

            $variant->update([
                'tenant_id' => $product->tenant_id,
                'product_id' => $productId,
                'name' => data_get($data, 'name', $variant->name),
                'slug' => $this->resolveSlug($variant, data_get($data, 'slug'), data_get($data, 'name', $variant->name), $productId),
                'seller_sku' => array_key_exists('seller_sku', $data) ? data_get($data, 'seller_sku') : $variant->seller_sku,
                'status' => data_get($data, 'status', $variant->status),
                'summary' => array_key_exists('summary', $data) ? data_get($data, 'summary') : $variant->summary,
                'description' => array_key_exists('description', $data) ? data_get($data, 'description') : $variant->description,
                'configuration' => array_key_exists('configuration', $data) ? data_get($data, 'configuration') : $variant->configuration,
                'is_default' => data_get($data, 'is_default', $variant->is_default),
                'is_active' => data_get($data, 'is_active', $variant->is_active),
                'metadata' => array_key_exists('metadata', $data) ? data_get($data, 'metadata') : $variant->metadata,
            ]);

            if (array_key_exists('category_ids', $data)) {
                $variant->categories()->sync(data_get($data, 'category_ids', []));
            }

            if (array_key_exists('feature_ids', $data)) {
                $variant->features()->sync(data_get($data, 'feature_ids', []));
            }

            if (array_key_exists('tag_ids', $data)) {
                $variant->tags()->sync(data_get($data, 'tag_ids', []));
            }

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

    private function buildRevisionPayload(Variant $variant): array
    {
        return [
            'tenant_id' => $variant->tenant_id,
            'product_id' => $variant->product_id,
            'name' => $variant->name,
            'slug' => $variant->slug,
            'status' => $variant->status,
            'summary' => $variant->summary,
            'description' => $variant->description,
            'configuration' => $variant->configuration,
            'is_default' => $variant->is_default,
            'is_active' => $variant->is_active,
            'metadata' => $variant->metadata,
            'category_ids' => $variant->categories()->pluck('categories.id')->all(),
            'feature_ids' => $variant->features()->pluck('features.id')->all(),
            'tag_ids' => $variant->tags()->pluck('tags.id')->all(),
        ];
    }

    private function resolveSlug(Variant $variant, ?string $slug, string $name, int $productId): string
    {
        $candidate = Str::slug($slug ?: $name);
        $base = $candidate;
        $suffix = 1;

        while (Variant::query()
            ->where('product_id', $productId)
            ->where('slug', $candidate)
            ->where('id', '!=', $variant->id)
            ->exists()) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
