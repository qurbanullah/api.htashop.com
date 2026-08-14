<?php

namespace App\Actions\Variant;

use App\Models\Variant;

class VariantDeleteAction
{
    public function handle(Variant $variant): bool
    {
        $variant->categories()->detach();
        $variant->features()->detach();
        $variant->tags()->detach();

        return (bool) $variant->delete();
    }
}