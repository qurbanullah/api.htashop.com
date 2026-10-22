<?php

namespace App\Services\Punchout;

use App\Actions\Cart\CartAddItemAction;
use App\Actions\Cart\CartReadAction;
use App\Actions\Cart\CartRemoveItemAction;
use App\Actions\Cart\CartUpdateItemAction;
use App\Actions\Punchout\CompletePunchoutSessionAction;
use App\Actions\Punchout\ResolvePunchoutPriceAction;
use App\Actions\Punchout\ValidatePunchoutReturnUrlAction;
use App\Enums\SalesChannel;
use App\Models\Cart;
use App\Models\Product;
use App\Models\PunchoutSession;
use App\Models\Variant;
use App\Support\Punchout\Data\PunchoutCartData;
use App\Support\Punchout\Data\PunchoutResponseData;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PunchoutCartService
{
    public function __construct(
        protected CartReadAction $readAction,
        protected CartAddItemAction $addItemAction,
        protected CartUpdateItemAction $updateItemAction,
        protected CartRemoveItemAction $removeItemAction,
        protected PunchoutProtocolService $protocolService,
        protected CompletePunchoutSessionAction $completePunchoutSessionAction,
        protected ValidatePunchoutReturnUrlAction $validatePunchoutReturnUrlAction,
        protected ResolvePunchoutPriceAction $resolvePunchoutPriceAction,
    ) {}

    public function read(PunchoutSession $session): array
    {
        return $this->readAction->handle($this->resolve($session));
    }

    public function addItem(PunchoutSession $session, array $data): array
    {
        $productId = (int) data_get($data, 'product_id');
        $this->assertProductAvailable($session, $productId);

        /** @var Product $product */
        $product = Product::query()->findOrFail($productId);
        $variant = data_get($data, 'variant_id')
            ? Variant::query()->find(data_get($data, 'variant_id'))
            : null;
        $quantity = max(1, (int) data_get($data, 'quantity', 1));

        $cart = $this->resolve($session);
        $existingQuantity = (int) $cart->items()
            ->where('product_id', $product->id)
            ->when(
                $variant,
                fn ($query) => $query->where('variant_id', $variant->id),
                fn ($query) => $query->whereNull('variant_id'),
            )
            ->value('quantity');

        // Price the resulting line, not just the increment: volume tiers are
        // defined against the total quantity on the line.
        $pricing = $this->resolvePunchoutPriceAction->handle(
            $session->tenant ?? $session->tenant()->firstOrFail(),
            $product,
            $variant,
            $existingQuantity + $quantity,
            $session->buyer,
        );

        $cart = $this->addItemAction->handle(
            $cart,
            $data,
            $pricing,
            $this->pricingMetadata($pricing),
        );

        return $this->readAction->handle($cart);
    }

    public function updateItem(PunchoutSession $session, string $itemUuid, int $quantity): array
    {
        $cart = $this->resolve($session);
        $item = $cart->items()->with(['product', 'variant'])->where('uuid', $itemUuid)->first();

        // Re-price on quantity change: volume tiers and contract thresholds are
        // evaluated against the new quantity. The cart action still owns the
        // existence and minimum-quantity checks.
        if ($item?->product) {
            $pricing = $this->resolvePunchoutPriceAction->handle(
                $session->tenant ?? $session->tenant()->firstOrFail(),
                $item->product,
                $item->variant,
                $quantity,
                $session->buyer,
            );

            $item->update([
                'unit_price' => $pricing['unit_price'],
                'base_price' => $pricing['base_price'],
                'currency' => $pricing['currency'],
                'metadata' => array_merge((array) $item->metadata, $this->pricingMetadata($pricing)),
            ]);
        }

        $cart = $this->updateItemAction->handle($cart, $itemUuid, $quantity);

        return $this->readAction->handle($cart);
    }

    public function removeItem(PunchoutSession $session, string $itemUuid): array
    {
        $cart = $this->removeItemAction->handle($this->resolve($session), $itemUuid);

        return $this->readAction->handle($cart);
    }

    public function clear(PunchoutSession $session): array
    {
        $cart = $this->resolve($session);
        $cart->items()->delete();

        return $this->readAction->handle($cart);
    }

    /**
     * Build the protocol payload from the server-side cart and render the
     * auto-submit response. The client never supplies prices or a return URL.
     */
    public function returnCart(PunchoutSession $session, ?string $note = null): PunchoutResponseData
    {
        $cart = $this->resolve($session);
        $lines = $cart->items()->with('product')->get();
        $maxLineItems = max(1, (int) data_get(
            $session->tenant?->settings,
            'punchout.max_line_items',
            config('punchout.max_line_items', 200),
        ));

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => ['The punchout cart is empty.'],
            ]);
        }

        if ($lines->count() > $maxLineItems) {
            throw ValidationException::withMessages([
                'cart' => ["The punchout cart exceeds the {$maxLineItems} line item limit."],
            ]);
        }

        $items = [];

        foreach ($lines as $line) {
            $product = $line->product;

            if (! $product || ! $this->isProductAvailable($session, (int) $product->id)) {
                throw ValidationException::withMessages([
                    'cart' => ['A product in the cart is no longer available in this punchout catalog.'],
                ]);
            }

            $items[] = [
                'supplier_part_id' => (string) (data_get($line->metadata, 'sku') ?: $product->sku ?: $product->part_number ?: $product->uuid),
                'customer_part_number' => data_get($line->metadata, 'customer_part_number'),
                'description' => (string) (data_get($line->metadata, 'name') ?: $product->name),
                'quantity' => (float) $line->quantity,
                'price' => number_format((float) $line->unit_price, 2, '.', ''),
                'currency' => (string) ($line->currency ?: 'USD'),
                'unit_of_measure' => (string) data_get($line->metadata, 'unit_of_measure', 'EA'),
            ];
        }

        $tenant = $session->tenant ?? $session->tenant()->firstOrFail();
        $configuration = $this->protocolService->protocolConfiguration($tenant, $session->protocol);
        $handler = $this->protocolService->resolve($tenant, $session->protocol);

        $this->validatePunchoutReturnUrlAction->handle($session);

        $cartData = new PunchoutCartData(
            protocol: $session->protocol,
            operation: (string) data_get($configuration, 'cart_operation', 'PunchOutOrderMessage'),
            sessionUuid: $session->uuid,
            sessionToken: $session->token,
            returnUrl: $session->return_url,
            buyerCookie: $session->buyer_cookie,
            items: $items,
            payload: [
                'source' => 'punchout_ui',
                'note' => $note,
            ],
        );

        return DB::transaction(function () use ($session, $cart, $cartData, $tenant, $configuration, $handler): PunchoutResponseData {
            $this->completePunchoutSessionAction->handle($session, $cartData);
            $cart->update(['status' => 'returned']);

            return $handler->renderCartResponse($tenant, $cartData, $configuration);
        });
    }

    private function resolve(PunchoutSession $session): Cart
    {
        $currency = (string) ($session->buyer?->default_currency
            ?: data_get($session->tenant?->settings, 'punchout.default_currency', 'USD'));

        return Cart::query()->firstOrCreate(
            ['punchout_session_id' => $session->id, 'status' => 'active'],
            ['currency' => $currency],
        );
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @return array<string, mixed>
     */
    private function pricingMetadata(array $pricing): array
    {
        return array_filter([
            'customer_part_number' => data_get($pricing, 'customer_part_number'),
            'unit_of_measure' => data_get($pricing, 'unit_of_measure') ?: 'EA',
            'price_source' => data_get($pricing, 'source'),
            'price_id' => data_get($pricing, 'price_id'),
            'contract_id' => data_get($pricing, 'contract_id'),
            'lead_time_days' => data_get($pricing, 'lead_time_days'),
        ], fn ($value) => $value !== null);
    }

    private function assertProductAvailable(PunchoutSession $session, int $productId): void
    {
        if (! $this->isProductAvailable($session, $productId)) {
            throw ValidationException::withMessages([
                'product_id' => ['The selected product is not available in this punchout catalog.'],
            ]);
        }
    }

    private function isProductAvailable(PunchoutSession $session, int $productId): bool
    {
        return Product::query()
            ->whereKey($productId)
            ->forTenant($session->tenant_id)
            ->forChannel(SalesChannel::PUNCHOUT)
            ->where('is_active', true)
            ->where('status', 'active')
            ->exists();
    }
}
