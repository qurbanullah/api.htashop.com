<?php

namespace App\Actions\Variant;

use App\Models\Variant;

class VariantSearchByUuidAction
{
    public function handle(string $uuid): Variant
    {
        return Variant::query()
            ->with([
                'dams.collections',
                'product.dams.collections',
                'product.tenant.dams.collections',
                'product.organization.dams.collections',
                'product',
                'categories',
                'features',
                'tags',
                'attributeValues.definition.measurement',
                'attributeValues.definition.labels',
                'attributeValues.option',
                'attributeValues.unit.measurement',
                'specificationValues.definition.measurement',
                'specificationValues.definition.labels',
                'specificationValues.option',
                'specificationValues.unit.measurement',
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
