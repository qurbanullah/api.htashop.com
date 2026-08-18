<?php

namespace App\Actions\ProductReview;

use App\Models\Product;

class ResolveProductAction
{
    public function handle(string $key): Product
    {
        return Product::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->where(function ($query) use ($key) {
                if ($this->isUuid($key)) {
                    $query->where('uuid', strtolower($key));

                    return;
                }

                $segments = explode('-', $key);
                $prefix = (string) end($segments);
                $query->where('uuid', 'like', $prefix . '%');
            })
            ->firstOrFail();
    }

    private function isUuid(string $key): bool
    {
        return preg_match('/^[0-9a-fA-F-]{36}$/', $key) === 1;
    }
}
