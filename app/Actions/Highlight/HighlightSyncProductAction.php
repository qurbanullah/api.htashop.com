<?php

namespace App\Actions\Highlight;

use App\Models\Product;

class HighlightSyncProductAction
{
    public function handle(string $uuid, array $items): Product
    {
        $product = Product::query()->where('uuid', $uuid)->firstOrFail();

        $sync = [];
        foreach ($items as $index => $item) {
            $highlightId = (int) data_get($item, 'highlight_id');
            if (! $highlightId) {
                continue;
            }

            $headingOverride = data_get($item, 'heading_override');
            $bodyOverride = data_get($item, 'body_override');

            $sync[$highlightId] = [
                'sort_order' => (int) data_get($item, 'sort_order', $index),
                // Store overrides as NULL when they are blank so the model's
                // default heading/body is used at read time.
                'heading_override' => $headingOverride !== null && $headingOverride !== '' ? $headingOverride : null,
                'body_override' => $bodyOverride !== null && $bodyOverride !== '' ? $bodyOverride : null,
            ];
        }

        $product->highlights()->sync($sync);

        return $product->load('highlights');
    }
}
