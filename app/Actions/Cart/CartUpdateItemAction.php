<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use Illuminate\Validation\ValidationException;

class CartUpdateItemAction
{
    public function handle(Cart $cart, string $itemUuid, int $quantity): Cart
    {
        $item = $cart->items()->where('uuid', $itemUuid)->first();

        if (! $item) {
            throw ValidationException::withMessages([
                'item' => 'Cart item not found.',
            ]);
        }

        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be at least 1.',
            ]);
        }

        $item->update(['quantity' => $quantity]);

        return $cart->load('items');
    }
}
