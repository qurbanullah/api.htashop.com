<?php

namespace App\Actions\Highlight;

use App\Models\Highlight;

class HighlightCreateAction
{
    public function handle(array $data): Highlight
    {
        $highlight = Highlight::create([
            'tenant_id' => data_get($data, 'tenant_id'),
            'label' => data_get($data, 'label'),
            'heading' => data_get($data, 'heading'),
            'body' => data_get($data, 'body'),
            'code' => data_get($data, 'code'),
            'is_active' => data_get($data, 'is_active', true),
            'sort_order' => data_get($data, 'sort_order', 0),
        ]);

        if (array_key_exists('category_ids', $data)) {
            $highlight->categories()->sync(array_map('intval', (array) $data['category_ids']));
        }

        return $highlight->load('categories');
    }
}
