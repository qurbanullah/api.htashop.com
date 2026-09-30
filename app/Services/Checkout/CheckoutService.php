<?php

namespace App\Services\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Gateways\PaymentGatewayManager;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Coupon\CouponException;
use App\Services\Coupon\CouponService;
use App\Services\Order\OrderNumberService;
use App\Support\Checkout\CheckoutTotals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager,
        protected OrderNumberService $orderNumberService,
        protected CartPricing $cartPricing,
        protected CheckoutPricing $checkoutPricing,
        protected CouponService $coupons,
    ) {}

    /**
     * Create an order from the current cart. Idempotent per checkout_token.
     */
    public function checkout(Request $request, array $data): array
    {
        // Reject an unavailable gateway before we create anything, so a
        // disabled method cannot leave a half-finished order behind.
        $paymentMethod = (string) data_get($data, 'payment_method', PaymentMethod::COD);

        if (! $this->gatewayManager->isAvailable($paymentMethod)) {
            throw ValidationException::withMessages([
                'payment_method' => 'The selected payment method is not available.',
            ]);
        }

        // Idempotency: the same checkout_token always returns the same order,
        // even if the cart was already consumed by a previous attempt.
        $token = data_get($data, 'checkout_token');
        if ($token) {
            $existing = Order::query()
                ->where('checkout_token', $token)
                ->with(['items', 'payments'])
                ->first();

            if ($existing) {
                // A retry after the gateway call failed still gets a usable
                // session: without this the customer would be stranded with a
                // saved order and no way to pay for it.
                $redirectUrl = $this->pendingCheckoutUrl($existing);

                if ($redirectUrl === null && $this->awaitsHostedPayment($existing)) {
                    [$redirectUrl, $paymentError] = $this->initializePayment($existing, $paymentMethod);

                    return $this->result(
                        $existing->fresh(['items', 'payments']),
                        $redirectUrl,
                        $paymentError,
                    );
                }

                return $this->result($existing, $redirectUrl);
            }
        }

        $cart = $this->resolveCart($request);

        if ($cart->items()->count() === 0) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        $user = $request->user('api');

        // Scope is resolved before pricing: a coupon belongs to an organization,
        // a tenant or the platform, and the same code can exist in every scope.
        $tenantId = $this->resolveTenantId($cart, $user);
        $tenant = Tenant::query()->find($tenantId);
        $organization = $user?->memberships()->where('is_active', true)->first()?->organization;

        // Money is decided here, never by the request: delivery, tax and any
        // coupon come from `config/shipping.php` and the coupon table. A
        // `shipping_fee` or `discount` in the payload is ignored.
        $totals = $this->checkoutPricing->forCart(
            $cart,
            data_get($data, 'coupon_code'),
            $user,
            $tenantId,
            $organization?->id,
        );

        $order = DB::transaction(function () use ($cart, $user, $tenantId, $tenant, $organization, $data, $token, $totals) {
            // Re-checked inside the transaction: stock can move between the quote
            // and the commit.
            $this->cartPricing->assertPurchasable($cart);

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
                'currency' => $totals->currency,
                'subtotal' => $totals->subtotal,
                'shipping_fee' => $totals->shippingFee,
                'tax' => $totals->tax,
                'discount' => $totals->discount,
                'total_amount' => $totals->total,
                'metadata' => array_filter([
                    'payment_method' => data_get($data, 'payment_method', PaymentMethod::COD),
                    'coupon' => $totals->couponCode ? [
                        'code' => $totals->couponCode,
                        'label' => $totals->couponLabel,
                        'discount' => $totals->discount,
                    ] : null,
                ]),
            ]);

            foreach ($cart->items()->with(['product', 'variant'])->get() as $item) {
                $pricing = $this->cartPricing->linePrice($item->product, $item->variant);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'name' => $item->product?->name ?? data_get($item->metadata, 'name'),
                    'sku' => $item->variant?->sku ?? $item->product?->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $pricing['unit_price'],
                    'base_price' => $pricing['base_price'],
                    'total' => round($pricing['unit_price'] * (float) $item->quantity, 2),
                    'currency' => $pricing['currency'],
                    'metadata' => $item->metadata,
                ]);
            }

            // Recorded inside the transaction so the use and the order commit
            // together — a redeemed coupon with no order would burn a use.
            if ($totals->couponCode) {
                $coupon = $this->coupons->resolve(
                    $totals->couponCode,
                    $totals->subtotal,
                    $user,
                    $cart->session_id,
                    $tenantId,
                    $organization?->id,
                );
                $this->coupons->redeem($coupon, $order, $totals->discount, $user, $cart->session_id);
            }

            // Cart is consumed by the order.
            $cart->items()->delete();

            return $order->fresh(['items', 'payments']);
        });

        // Gateway initialization runs *after* the commit. A hosted gateway is an
        // outbound HTTP call, and holding a write transaction open across it
        // would pin row locks for as long as the provider takes to answer —
        // with several API replicas that is a self-inflicted outage.
        [$redirectUrl, $paymentError] = $this->initializePayment($order, $paymentMethod);

        return $this->result($order->fresh(['items', 'payments']), $redirectUrl, $paymentError);
    }

    /**
     * Open the gateway's payment session for an order.
     *
     * A provider outage must not turn a placed order into a 500 — the order is
     * valid and the customer can retry against it — so the failure is reported
     * back to the caller instead of thrown.
     *
     * @return array{0: ?string, 1: ?string} [redirectUrl, paymentError]
     */
    private function initializePayment(Order $order, string $paymentMethod): array
    {
        try {
            $redirectUrl = $this->gatewayManager
                ->gateway($paymentMethod)
                ->initialize($order)
                ->redirectUrl;

            return [$redirectUrl, null];
        } catch (\Throwable $e) {
            report($e);

            return [null, 'We could not start the payment. Please try again.'];
        }
    }

    /**
     * Whether the order still needs a session with a hosted gateway, i.e. it has
     * a pending payment for a method that redirects the customer away.
     */
    private function awaitsHostedPayment(Order $order): bool
    {
        return $order->payments->contains(
            fn (Payment $payment) => $payment->status === PaymentStatus::PENDING
                && $payment->payment_method !== PaymentMethod::COD
        );
    }

    public function read(string $uuid): Order
    {
        return Order::query()
            ->with(['items', 'payments'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * Price the current cart without creating anything.
     *
     * Shares `CheckoutPricing` with `checkout()`, so the quote the customer is
     * shown is the arithmetic the order is placed with. A bad coupon throws
     * `CouponException`; the controller turns that into a 422.
     */
    public function quote(Request $request, ?string $couponCode): CheckoutTotals
    {
        $cart = $this->resolveCart($request);

        if ($cart->items()->count() === 0) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        $user = $request->user('api');

        // The quote must price the coupon in the same scope the order will, or
        // the customer would see one discount and be charged another.
        $tenantId = $this->resolveTenantId($cart, $user);
        $organization = $user?->memberships()->where('is_active', true)->first()?->organization;

        return $this->checkoutPricing->forCart($cart, $couponCode, $user, $tenantId, $organization?->id);
    }

    private function result(Order $order, ?string $redirectUrl = null, ?string $paymentError = null): array
    {
        return [
            'order' => $order,
            'payment' => $order->payments->first(),
            'requires_redirect' => $redirectUrl !== null,
            'redirect_url' => $redirectUrl,
            'payment_error' => $paymentError,
        ];
    }

    /**
     * The hosted checkout URL of an order's still-pending payment, if we
     * already opened one. A repeated checkout with the same token reuses it.
     */
    private function pendingCheckoutUrl(Order $order): ?string
    {
        $payment = $order->payments
            ->firstWhere('status', PaymentStatus::PENDING);

        $url = data_get($payment?->gateway_payload, 'checkout_url');

        return is_string($url) && $url !== '' ? $url : null;
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

        if ($owner instanceof Organization) {
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
}
