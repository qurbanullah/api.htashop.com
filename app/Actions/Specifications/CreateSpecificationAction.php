<?php

namespace App\Actions\Specifications;

use App\Models\Specification;
use Illuminate\Support\Str;

class CreateSpecificationAction
{
    public function execute(array $data): Specification
    {
        $name = data_get($data, 'name');
        $slug = data_get($data, 'slug', Str::slug($name));
        $type = data_get($data, 'type', 'product');

        return Specification::create([
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
            'unit' => data_get($data, 'unit'),
            'group' => data_get($data, 'group'),
            'description' => data_get($data, 'description'),
            'usage_count' => 0,
            'sort_order' => data_get($data, 'sort_order', 0),
        ]);
    }
}
