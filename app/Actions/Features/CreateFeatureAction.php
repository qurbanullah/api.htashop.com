<?php

namespace App\Actions\Features;

use App\Models\Feature;
use Illuminate\Support\Str;

class CreateFeatureAction
{
    public function execute(array $data): Feature
    {
        $name = data_get($data, 'name');
        $slug = data_get($data, 'slug', Str::slug($name));
        $type = data_get($data, 'type', 'product');

        return Feature::create([
            'name' => $name,
            'slug' => $slug,
            'icon' => data_get($data, 'icon'),
            'type' => $type,
            'description' => data_get($data, 'description'),
            'usage_count' => 0,
            'sort_order' => data_get($data, 'sort_order', 0),
        ]);
    }
}
