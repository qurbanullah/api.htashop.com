<?php

namespace App\Actions\Highlight;

use App\Models\Highlight;

class HighlightUpdateAction
{
    public function handle(Highlight $highlight, array $data): Highlight
    {
        $highlight->update([
            'label' => data_get($data, 'label', $highlight->label),
            'heading' => data_get($data, 'heading', $highlight->heading),
            'body' => data_get($data, 'body', $highlight->body),
            'code' => data_get($data, 'code', $highlight->code),
            'is_active' => data_get($data, 'is_active', $highlight->is_active),
            'sort_order' => data_get($data, 'sort_order', $highlight->sort_order),
        ]);

        if (array_key_exists('category_ids', $data)) {
            $highlight->categories()->sync(array_map('intval', (array) $data['category_ids']));
        }

        return $highlight->fresh('categories');
    }
}
