<?php

namespace App\Actions\Product;

use App\Models\Product;

class ProductDeleteAction
{
    public function handle(Product $product): bool
    {
        $product->categories()->detach();
        $product->features()->detach();
        $product->tags()->detach();

        return (bool) $product->delete();
    }
}