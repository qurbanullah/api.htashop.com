<?php

namespace App\Services\Checkout;

use App\Enums\PaymentMethod;
use App\Enums\OrderStatus;
use App\Gateways\PaymentGatewayManager;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Variant;
use App\Services\Order\OrderNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager,
        protected OrderNumberService $orderNumberService,
    ) {
    }

    /**
     * Create an order from the current cart. Idempotent per checkout_token.
     */
    public function checkout(Request $request, array $data): array
    {
        // Idempotency: the same checkout_token always returns the same order,
        // even if the cart was already consumed by a previous attempt.
        $token = data_get($data, 'checkout_token');
        if ($token) {
            $existing = Order::query()
                ->where('checkout_token', $token)
                ->with(['items', 'payments'])
                ->first();

            if ($existing) {
                return $this->result($existing);
            }
        }

        $cart = $this->resolveCart($request);

        if ($cart->items()->count() === 0) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        $user = $request->user('api');
        $tenantId = $this->resolveTenantId($cart, $user);
        $tenant = Tenant::query()->find($tenantId);
        $organization = $user?->memberships()->where('is_active', true)->first()?->organization;

        return DB::transaction(function () use ($cart, $user, $tenantId, $tenant, $organization, $data, $token) {
            /** @var Order $order */
            $order = Order::create([
                'tenant_id' => $tenantId,
                'user_id' => $user?->id,
                'organization_id' => $organization?->id,
                'order_number' => $tenant ? $this->orderNumberService->generate($tenant, $organization) : null,
                'checkout_token' => $token,
                'customer_name' => data_get($data, 'customer_name') ?? $user?->name,
                'customer_email' => data_get($data, 'customer_email') ?? $user?->email,
                'customer_phone' => data_get($data, 'customer_phone'),
                'status' => OrderStatus::PENDING,
                'source' => 'manual',
                'shipping_address' => $this->resolveAddress($data, 'shipping_address', 'shipping', $user),
                'billing_address' => $this->resolveAddress($data, 'billing_address', 'billing', $user)
                    ?? $this->resolveAddress($data, 'shipping_address', 'shipping', $user),
                'notes' => data_get($data, 'notes'),
                'placed_at' => now(),
                'currency' => $cart->currency,
                'metadata' => [
                    'payment_method' => data_get($data, 'payment_method', PaymentMethod::COD),
                ],
            ]);

            $subtotal = 0.0;

            foreach ($cart->items()->with(['product', 'variant'])->get() as $item) {
                $this->validateItemStock($item->product, $item->variant, (int) $item->quantity);

                $pricing = $this->resolvePricing($item->product, $item->variant);
                $lineTotal = round($pricing['unit_price'] * (float) $item->quantity, 2);
                $subtotal += $lineTotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'name' => $item->product?->name ?? data_get($item->metadata, 'name'),
                    'sku' => $item->variant?->sku ?? $item->product?->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $pricing['unit_price'],
                    'base_price' => $pricing['base_price'],
                    'total' => $lineTotal,
                    'currency' => $pricing['currency'],
                    'metadata' => $item->metadata,
                ]);
            }

            $shippingFee = (float) data_get($data, 'shipping_fee', 0);
            $tax = (float) data_get($data, 'tax', 0);
            $discount = (float) data_get($data, 'discount', 0);

            $order->update([
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'tax' => $tax,
                'discount' => $discount,
                'total_amount' => round($subtotal + $shippingFee + $tax - $discount, 2),
            ]);

            // Payment record + gateway initialization (COD: no redirect).
            $gateway = $this->gatewayManager->gateway(data_get($data, 'payment_method', PaymentMethod::COD));
            $initResult = $gateway->initialize($order->fresh());

            // Cart is consumed by the order.
            $cart->items()->delete();

            return $this->result($order->fresh(['items', 'payments']), $initResult->redirectUrl);
        });
    }

    public function read(string $uuid): Order
    {
        return Order::query()
            ->with(['items', 'payments'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    private function result(Order $order, ?string $redirectUrl = null): array
    {
        return [
            'order' => $order,
            'payment' => $order->payments->first(),
            'requires_redirect' => $redirectUrl !== null,
            'redirect_url' => $redirectUrl,
        ];
    }

    private function resolveCart(Request $request): Cart
    {
        $user = $request->user('api');
        $sessionId = $request->header('X-Cart-Token') ?: $request->input('cart_token');

        $cart = null;
        if ($user) {
            $cart = Cart::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();
        }

        if (! $cart && $sessionId) {
            $cart = Cart::query()
                ->where('session_id', $sessionId)
                ->where('status', 'active')
                ->first();
        }

        if (! $cart) {
            throw ValidationException::withMessages(['cart' => 'No active cart found.']);
        }

        return $cart;
    }

    private function resolveTenantId(Cart $cart, ?User $user): int
    {
        if ($user) {
            $membership = $user->memberships()->where('is_active', true)->first();
            if ($membership?->tenant_id) {
                return $membership->tenant_id;
            }
        }

        return Tenant::query()->value('id')
            ?? throw ValidationException::withMessages(['cart' => 'No tenant configured.']);
    }

    private function resolveOrganizationId(?User $user): ?int
    {
        return $user?->memberships()->where('is_active', true)->first()?->organization_id;
    }

    /**
     * Resolve an address: from the address book (uuid string) or inline payload.
     */
    private function resolveAddress(array $data, string $key, string $type, ?User $user): ?array
    {
        $payload = data_get($data, $key);

        if (is_array($payload)) {
            return $this->snapshot($payload);
        }

        if (is_string($payload) && $payload !== '') {
            $address = Address::query()
                ->where('uuid', $payload)
                ->with('country')
                ->first();

            if ($address && ($user === null || $this->ownsAddress($address, $user))) {
                return $this->snapshot([
                    'label' => $address->label,
                    'contact_name' => $address->contact_name,
                    'phone' => $address->phone,
                    'email' => $address->email,
                    'address_line_1' => $address->address_line_1,
                    'address_line_2' => $address->address_line_2,
                    'city' => $address->city,
                    'city_id' => $address->city_id,
                    'state' => $address->state,
                    'state_code' => $address->state_code,
                    'postal_code' => $address->postal_code,
                    'country_id' => $address->country_id,
                    'country' => $address->country?->name,
                ]);
            }
        }

        return null;
    }

    private function ownsAddress(Address $address, User $user): bool
    {
        $owner = $address->addressable;

        if ($owner instanceof User) {
            return $owner->is($user);
        }

        if ($owner instanceof \App\Models\Organization) {
            return $user->memberships()
                ->where('is_active', true)
                ->where('organization_id', $owner->id)
                ->exists();
        }

        return false;
    }

    private function snapshot(array $data): array
    {
        return [
            'label' => data_get($data, 'label'),
            'contact_name' => data_get($data, 'contact_name'),
            'phone' => data_get($data, 'phone'),
            'email' => data_get($data, 'email'),
            'address_line_1' => data_get($data, 'address_line_1'),
            'address_line_2' => data_get($data, 'address_line_2'),
            'city' => data_get($data, 'city'),
            'city_id' => data_get($data, 'city_id'),
            'state' => data_get($data, 'state'),
            'state_code' => data_get($data, 'state_code'),
            'postal_code' => data_get($data, 'postal_code'),
            'country_id' => data_get($data, 'country_id'),
            'country' => data_get($data, 'country') ?? data_get($data, 'country.name'),
        ];
    }

    private function validateItemStock(?Product $product, ?Variant $variant, int $quantity): void
    {
        if (! $product || ! $product->is_active || $product->status !== 'active') {
            $name = $product?->name ?? 'Product';
            throw ValidationException::withMessages(['cart' => "The product \"{$name}\" is not available."]);
        }

        $stockable = $variant ?? $product;
        $inventories = $stockable->inventories()->where('track_inventory', true)->get();

        if ($inventories->isEmpty()) {
            return;
        }

        $available = $inventories->sum(fn ($inventory) => max(0, (float) $inventory->available));

        if ($available < $quantity) {
            throw ValidationException::withMessages([
                'cart' => 'Insufficient stock for "' . ($variant?->name ?? $product->name) . '".',
            ]);
        }
    }

    private function resolvePricing(?Product $product, ?Variant $variant): array
    {
        $metadata = $variant?->metadata ?? $product?->metadata ?? [];

        $base = (float) data_get($metadata, 'price', 0);
        $sale = (float) data_get($metadata, 'sale_price', 0);
        $currency = data_get($metadata, 'currency', 'USD') ?: 'USD';

        $effective = ($sale > 0 && $sale < $base) ? $sale : $base;

        return [
            'unit_price' => $effective,
            'base_price' => $base > 0 ? $base : null,
            'currency' => $currency,
        ];
    }
}
