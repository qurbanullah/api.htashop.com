<?php

namespace App\Actions\Highlight;

use App\Models\Product;

class HighlightReadProductAction
{
    public function handle(string $uuid): array
    {
        $product = Product::query()
            ->where('uuid', $uuid)
            ->with(['highlights' => fn ($query) => $query->orderByPivot('sort_order')])
            ->firstOrFail();

        return $product->highlights->map(fn ($highlight) => [
            'highlight_id' => $highlight->id,
            'label' => $highlight->label,
            'heading' => $this->overrideValue($highlight->pivot->heading_override, $highlight->heading),
            'body' => $this->overrideValue($highlight->pivot->body_override, $highlight->body),
            'heading_override' => $highlight->pivot->heading_override,
            'body_override' => $highlight->pivot->body_override,
            'sort_order' => $highlight->pivot->sort_order,
        ])->values()->all();
    }

    private function overrideValue(?string $override, ?string $fallback): ?string
    {
        return $override !== null && $override !== '' ? $override : $fallback;
    }
}
