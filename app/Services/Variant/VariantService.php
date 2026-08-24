<?php

namespace App\Services\Variant;

use App\Actions\Variant\VariantUpdateAction;
use App\Models\Variant;
use Illuminate\Pagination\LengthAwarePaginator;

class VariantService
{
    public function __construct(
        protected VariantUpdateAction $updateAction,
    ) {
    }

    public function read(array $filters = []): LengthAwarePaginator
    {
        return Variant::query()
            ->when(data_get($filters, 'product_id'), fn ($q, $id) => $q->where('product_id', $id))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate((int) data_get($filters, 'per_page', 100));
    }

    public function searchByUuid(string $uuid): Variant
    {
        return Variant::where('uuid', $uuid)->firstOrFail();
    }

    public function create(array $data): Variant
    {
        // Auto-generate slug from name
        if (empty($data['slug']) && !empty($data['name'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['name']);
        }

        // Set default if this is the first variant
        if (!empty($data['product_id']) && empty($data['is_default'])) {
            $exists = Variant::where('product_id', $data['product_id'])->exists();
            if (!$exists) {
                $data['is_default'] = true;
            }
        }

        // Unset others if this one is default
        if (!empty($data['is_default']) && !empty($data['product_id'])) {
            Variant::where('product_id', $data['product_id'])
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        return Variant::create($data);
    }

    public function update(Variant $variant, array $data): Variant
    {
        // Unset others if this one is becoming default
        if (!empty($data['is_default']) && $variant->product_id) {
            Variant::where('product_id', $variant->product_id)
                ->where('is_default', true)
                ->where('id', '!=', $variant->id)
                ->update(['is_default' => false]);
        }

        return $this->updateAction->handle($variant, $data);
    }

    public function delete(Variant $variant): bool
    {
        return $variant->delete() ?? false;
    }
}
