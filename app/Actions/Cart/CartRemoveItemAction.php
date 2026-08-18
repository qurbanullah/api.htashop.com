<?php

namespace App\Actions\Cart;

use App\Models\Cart;

class CartRemoveItemAction
{
    public function handle(Cart $cart, string $itemUuid): Cart
    {
        $cart->items()->where('uuid', $itemUuid)->delete();

        return $cart->load('items');
    }
}
