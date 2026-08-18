<?php

namespace App\Services\Cart;

use App\Actions\Cart\CartAddItemAction;
use App\Actions\Cart\CartReadAction;
use App\Actions\Cart\CartRemoveItemAction;
use App\Actions\Cart\CartUpdateItemAction;
use App\Models\Cart;
use Illuminate\Http\Request;

class CartService
{
    public function __construct(
        protected CartReadAction $readAction,
        protected CartAddItemAction $addItemAction,
        protected CartUpdateItemAction $updateItemAction,
        protected CartRemoveItemAction $removeItemAction,
    ) {
    }

    public function read(Request $request): array
    {
        return $this->readAction->handle($this->resolve($request));
    }

    public function addItem(Request $request, array $data): array
    {
        $cart = $this->addItemAction->handle($this->resolve($request), $data);

        return $this->readAction->handle($cart);
    }

    public function updateItem(Request $request, string $itemUuid, int $quantity): array
    {
        $cart = $this->updateItemAction->handle($this->resolve($request), $itemUuid, $quantity);

        return $this->readAction->handle($cart);
    }

    public function removeItem(Request $request, string $itemUuid): array
    {
        $cart = $this->removeItemAction->handle($this->resolve($request), $itemUuid);

        return $this->readAction->handle($cart);
    }

    public function clear(Request $request): array
    {
        $cart = $this->resolve($request);
        $cart->items()->delete();

        return $this->readAction->handle($cart);
    }

    private function resolve(Request $request): Cart
    {
        $user = $request->user('api');
        $sessionId = $request->header('X-Cart-Token') ?: $request->input('cart_token');

        if ($user) {
            $userCart = Cart::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first() ?? Cart::create(['user_id' => $user->id, 'currency' => 'USD']);

            if ($sessionId) {
                $guestCart = Cart::query()
                    ->where('session_id', $sessionId)
                    ->whereNull('user_id')
                    ->where('status', 'active')
                    ->first();

                if ($guestCart && $guestCart->id !== $userCart->id) {
                    $this->merge($guestCart, $userCart);
                }
            }

            return $userCart;
        }

        if ($sessionId) {
            return Cart::query()
                ->where('session_id', $sessionId)
                ->whereNull('user_id')
                ->where('status', 'active')
                ->first() ?? Cart::create(['session_id' => $sessionId, 'currency' => 'USD']);
        }

        return Cart::create(['currency' => 'USD']);
    }

    private function merge(Cart $guestCart, Cart $userCart): void
    {
        foreach ($guestCart->items()->get() as $guestItem) {
            $existing = $userCart->items()
                ->where('product_id', $guestItem->product_id)
                ->when($guestItem->variant_id, fn ($query) => $query->where('variant_id', $guestItem->variant_id), fn ($query) => $query->whereNull('variant_id'))
                ->first();

            if ($existing) {
                $existing->update(['quantity' => $existing->quantity + $guestItem->quantity]);
            } else {
                $userCart->items()->create([
                    'product_id' => $guestItem->product_id,
                    'variant_id' => $guestItem->variant_id,
                    'quantity' => $guestItem->quantity,
                    'unit_price' => $guestItem->unit_price,
                    'base_price' => $guestItem->base_price,
                    'currency' => $guestItem->currency,
                    'metadata' => $guestItem->metadata,
                ]);
            }
        }

        $guestCart->update(['status' => 'merged']);
    }
}
