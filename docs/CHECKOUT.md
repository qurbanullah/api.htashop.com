# Checkout pricing

How HTAShop decides what an order costs: delivery, tax and discount codes.

## Money is server-authoritative

The client never sends amounts. `POST /checkout` accepts addresses, a payment
method, an optional `coupon_code` and notes — never `shipping_fee`, `tax` or
`discount`. Those keys are not read, so a crafted request cannot discount
itself; the totals are computed inside `CheckoutService` and written to the
order. `POST /checkout/quote` runs the *same* calculation without creating
anything, which is what the storefront displays.

```
CheckoutController ─▶ CheckoutService ──▶ CheckoutPricing ──▶ CartPricing
                          │                    │  │              (item prices)
                          │                    │  └─▶ CouponService   (discount)
                          │                    └────▶ config/shipping.php (delivery + tax)
                          └─▶ Order + OrderItems + CouponRedemption
```

`CheckoutTotals` (`App\Support\Checkout\CheckoutTotals`) is the single shape both
endpoints return, so a quote and an order can never disagree.

## Endpoints

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| `POST` | `/api/v1/checkout/quote` | public (throttled 60/min per IP) | Price the current cart |
| `POST` | `/api/v1/checkout` | public | Place the order |

Both identify the cart the same way: `X-Cart-Token` header or `cart_token` in
the body. An unusable `coupon_code` on either endpoint is a **422** whose `reason`
is a stable token (see below) — not a 500, because a mistyped code is an
ordinary thing for a customer to do.

### Quote response

```json
{
  "subtotal": 2000, "shipping_fee": 250, "tax": 0, "discount": 300,
  "total": 1950, "currency": "PKR",
  "free_shipping": false, "free_shipping_threshold": 10000,
  "amount_until_free_shipping": 8000,
  "coupon_code": "EID10", "coupon_label": "Eid sale 10%"
}
```

`amount_until_free_shipping` lets the storefront say "spend PKR 8,000 more for
free delivery" without knowing the rule.

## Delivery & tax — `config/shipping.php`

- **Per currency.** `rates.PKR`, `rates.USD`, … with a `default` for anything
  unlisted. `flat_rate` is charged below the threshold.
- **Free over.** `free_over` is the subtotal at or above which delivery is free.
  `null` (or `0`) means "never free". Reaching it is `>=`.
- **Master switch.** `enabled = false` makes every order ship free and removes
  the line from the storefront.
- **Tax is off by default.** Pakistani retail prices are normally quoted
  tax-inclusive; charging `tax.rate` on top would quietly raise every basket.
  When enabled it applies to the *discounted* subtotal — a customer should not
  pay tax on money they never handed over.

All values are env-driven (`SHIPPING_*`, `TAX_*`); the config file holds the
sensible defaults.

## Coupons

A coupon is a row in `coupons`; every use is recorded in `coupon_redemptions`
(append-only). The fast usage counter (`used_count`) exists for the limit check,
the redemptions table is the source of truth — and what lets a cancellation hand
the use back.

### Rules

`CouponService::resolve()` decides usability, in order:

| Check | Reason token |
| --- | --- |
| Code exists (case-insensitively) | `coupon_not_found` |
| `is_active` | `coupon_inactive` |
| `starts_at` not in the future | `coupon_not_started` |
| `ends_at` not in the past | `coupon_expired` |
| subtotal ≥ `min_order_amount` | `coupon_min_order` |
| `used_count` < `usage_limit` | `coupon_usage_limit_reached` |
| redemptions for this customer < `per_user_limit` | `coupon_already_used` |

### Maths

- `fixed`: the value off, in the order currency.
- `percent`: `subtotal × value / 100`, capped by `max_discount_amount` when set.
- A discount is **clamped to the subtotal** (an order cannot go negative) and
  floored at zero. Codes are stored upper-case, so `save10` and `SAVE10` are the
  same coupon.

### Concurrency

`redeem()` runs inside the order's transaction and re-reads the coupon with
`lockForUpdate()` before incrementing, so two customers racing for the last use
of a limited code cannot both win — which a bare `increment()` would allow with
several API replicas. Redemption is recorded in the same transaction as the
order: a redeemed coupon with no order would burn a use.

Cancelling or refunding an order calls `CouponService::releaseFor()`, which
deletes the redemption and decrements `used_count` under a lock. Without it, a
cancellation would permanently consume one of a limited coupon's uses.

## Seeding a code

```php
App\Models\Coupon::create([
    'code' => 'EID10',
    'label' => 'Eid sale 10%',
    'type' => App\Enums\CouponType::PERCENT,
    'value' => 10,
    'max_discount_amount' => 1000,
    'min_order_amount' => 2000,
    'usage_limit' => 500,
    'per_user_limit' => 1,
    'is_active' => true,
]);
```

## Tests

`tests/Feature/Checkout/CheckoutPricingTest.php` pins the delivery thresholds,
the coupon maths (cap, clamp, case), each rejection reason, redemption counting,
release-on-cancel, and the security regression: a request that sends
`discount: 9999` must not reduce the total.
