# Payments

How HTAShop takes money. Covers the gateway abstraction, SafePay specifically,
and what to do when JazzCash / EasyPaisa / UPaisa are added.

## Design in one paragraph

Checkout only ever talks to `App\Gateways\PaymentGateway`. Which gateway serves
which method, and whether it is offered at all, comes from
`config/payment.php`. Adding a provider means adding one class and one config
entry — no change to order creation, the ledger, the order state machine or the
storefront.

```
CheckoutController ─▶ CheckoutService ─▶ PaymentGatewayManager ─▶ PaymentGateway
                                                                        │
                                              CodGateway  SafepayGateway ─┘
                                                                        │
                     PaymentReconciler ◀── webhook / verify / refund ───┘
                              │
                    payments + transactions + orders
```

## Availability rules

A method is offered at checkout when **both** are true:

1. the master switch `payment.enabled` is on, **and**
2. the gateway's own `enabled` flag is on, **and**
3. it has credentials, **and**
4. the order currency is in the gateway's `currencies` list.

Anything missing means "not offered", never "error". A gateway switched on
before its keys exist is simply absent from the list.

**Cash on Delivery cannot be disabled.** It has no credentials and no switch.
When every hosted gateway is off or unconfigured, `GET /payments/methods`
returns COD alone, and checkout still works.

## SafePay

### What you need

From the SafePay dashboard:

| Where | What | Env var |
| --- | --- | --- |
| Developer → Keys | public key (identifies the merchant) | `SAFEPAY_PUBLIC_API_KEY` |
| Developer → Keys | secret key (authenticates this server) | `SAFEPAY_SECRET_API_KEY` |
| Developer → Endpoints | webhook signing secret | `SAFEPAY_WEBHOOK_SECRET` |

```dotenv
PAYMENTS_ENABLED=true
PAYMENT_CURRENCY=PKR
SAFEPAY_ENABLED=true
SAFEPAY_ENVIRONMENT=sandbox          # production when going live
SAFEPAY_PUBLIC_API_KEY=...
SAFEPAY_SECRET_API_KEY=...
SAFEPAY_WEBHOOK_SECRET=...
```

`SAFEPAY_ENVIRONMENT` selects both hosts:

| | API | Hosted checkout |
| --- | --- | --- |
| `production` | `https://api.getsafepay.com` | `https://getsafepay.com` |
| `sandbox` | `https://sandbox.api.getsafepay.com` | `https://sandbox.api.getsafepay.com` |
| `development` | `https://dev.api.getsafepay.com` | `https://dev.api.getsafepay.com` |

Amounts are sent in minor units (paisas), rounded — `1500.00 PKR` goes out as
`150000`.

### The flow

1. `POST /checkout` with `payment_method=safepay`.
2. The order and a `pending` payment are created and committed.
3. **After** the commit, `SafepayGateway::initialize()`:
   - `POST /order/payments/v3/` → tracker token
   - `POST /order/payments/v3/{tracker}/metadata` → carries `order_id` and
     `source` only. SafePay accepts a very small, undocumented set of meta keys
     and 400s on anything else (`payment_uuid`, `order_number`, `reference`),
     so the call is **best-effort**: a rejection is logged and the session
     continues. A webhook that carries no usable metadata is still matched by
     its tracker token (see `resolvePayment()`).
   - `POST /client/passport/v1/token` → short-lived token (SafePay answers with
     a bare string under `data`, not an object)
   - builds `{checkoutBase}/embedded?tbt=…&tracker=…&order_id=…&environment=…&source=…&redirect_url=…&cancel_url=…`
4. The response carries `requires_redirect: true` and `redirect_url`. Send the
   customer there.
5. SafePay sends the browser back to `redirect_url` (see below), and calls the
   webhook. **The webhook is the authoritative signal**; the redirect is only a
   UX affordance and its parameters are not trusted.

#### `source` is not free-form

SafePay's hosted page switches on `source` to decide how to finish the flow, and
an **unrecognised value matches no branch at all** — the customer pays and is then
left on SafePay's own `/embedded/external/complete` page with no way back. The
values, read out of SafePay's checkout bundle (`static/js/main*.js`), are:

| `source` | Finish behaviour |
| --- | --- |
| `hosted` | auto-clicks a hidden link to `redirect_url` (what we use) |
| `woocommerce` / `shopify` | plugin integrations; navigate to `redirect_url` |
| `mobile` | for a native WebView; the host app handles completion |
| `popup` | postMessage to the opener |
| `xcomponent` | zoid cross-component callback |

The default lives in `config/payment.php` (`SAFEPAY_CHECKOUT_SOURCE`) and is
pinned by a test, because getting it wrong fails silently and only after a real
payment.

#### The return trip

For `hosted`, SafePay returns the customer with a **GET** to
`{redirect_url}?order_id=…&tracker=…`. That means `redirect_url` must be a URL
that already has no query string of its own, and the storefront route must
ignore the extra parameters — `PaymentReturnPage` identifies the payment from
`sessionStorage`, not from the query string, then polls
`GET /payments/{uuid}?refresh=1` so the status still comes from us.

Gateway calls happen outside the database transaction on purpose: holding a
write transaction open across a third-party HTTP call pins row locks for as long
as the provider takes to answer.

If SafePay cannot be reached, the order still exists and the response carries
`payment_error`. The customer can retry against the same order.

### Webhook

Register `POST /api/v1/payments/webhooks/safepay` in the dashboard, and put the
endpoint's signing secret in `SAFEPAY_WEBHOOK_SECRET`.

- Signature: `X-SFPY-SIGNATURE`, HMAC-SHA512 (hex) of the raw body, keyed with
  the webhook secret. Verified in constant time.
- Verified but not actionable → `200` with `handled: false` (no retry storm).
- Signature missing or wrong → `400`. Never a `200`: a non-2xx is how the
  dashboard tells you the secret is wrong.
- Verified events never `5xx` for our own logic errors → those return `500` so
  SafePay retries.

Events applied: `payment.succeeded`, `payment.failed`, `payment.refunded`,
`subscription.payment.succeeded`, `authorization.reversed`, `void.succeeded`.
Anything else is accepted and ignored.

Delivery is **at-least-once**, so `PaymentReconciler::sync()` is idempotent: it
locks the payment row, applies only legal transitions, and posts to the ledger
at most once per outcome. A duplicate `payment.succeeded` cannot double-post; a
late `payment.failed` cannot un-pay a succeeded payment.

Every accepted payload is appended to `payments.gateway_payload.webhook_events`
(capped at the last 20) with an `outcome` tag. Events we chose not to apply are
tagged `ignored`.

## Endpoints

### `GET /api/v1/payments/methods`

Public. `?currency=` optional; defaults to `payment.currency`.

```json
{
  "success": true,
  "data": {
    "methods": [
      { "method": "safepay", "label": "Safepay", "requires_redirect": true },
      { "method": "cod", "label": "Cash on Delivery", "requires_redirect": false }
    ]
  }
}
```

### `GET /api/v1/payments/{uuid}`

Public. Returns local state. `?refresh=1` asks the gateway for the authoritative
status first — slower, and the reason to use it is a webhook that never
arrived. The UUID is unguessable, which is what lets a guest poll their own
payment.

### `POST /api/v1/payments/webhooks/{method}`

Public, signature-verified, throttled per IP
(`PAYMENT_WEBHOOK_RATE_LIMIT`, default 120/min). Unknown methods `404`.

### `POST /api/v1/payments/{uuid}/refund`

**Admin only** (`admin` middleware). Body: `{ "amount": 500.00, "reason": "…" }`.
Omit `amount` for a full refund. Refunds a `paid` payment only.

## Storefront integration

The storefront implements this contract in `frontend/` — see
`frontend/CHECKOUT.md` for the UI side.

1. `GET /api/v1/payments/methods?currency=<cart currency>` and render the list.
   Methods are never hard-coded; enabling a gateway in `config/payment.php`
   makes it appear.
2. `POST /api/v1/checkout` with the chosen `payment_method`. If
   `requires_redirect`, send the customer to `redirect_url`. COD returns
   `requires_redirect: false` and is done.
3. Before redirecting, the storefront records the payment UUID and order UUID in
   `sessionStorage` (the gateway hands nothing back we can trust), then
   reconciles on return via `GET /payments/{uuid}?refresh=1`.
4. If `payment_error` is present, the order exists but payment did not start:
   surface it and allow a retry, rather than claiming success. Re-posting the
   same `checkout_token` re-opens the gateway session, so the retry works
   without a new endpoint.

The return URLs the gateway is given default to
`{FRONTEND_URL}/checkout/success` and `{FRONTEND_URL}/checkout/cancel`, which the
storefront serves as `paths.checkoutSuccess` / `paths.checkoutCancel`.

## Adding JazzCash / EasyPaisa / UPaisa

1. Add the method value to `App\Enums\PaymentMethod` and its label.
2. Write `App\Gateways\JazzcashGateway implements PaymentGateway`
   (`method`, `label`, `isEnabled`, `supportsCurrency`, `initialize`, `verify`,
   `handleCallback`, `refund`). Only `isEnabled()` has to decide whether
   credentials are present; `PaymentInitResult` carries the redirect.
3. Register the driver with its config slice in
   `AppServiceProvider::registerPayments()`.
4. Add a `jazzcash` entry to `config/payment.php` (there is a commented
   template) and the env keys.

Nothing in `CheckoutService`, `PaymentReconciler`, `PaymentService`, the
controllers, the routes or the schema changes.

Providers whose protocol already fits (a redirect plus a signed callback) need
no new infrastructure at all.

## Operations

- **Reconciliation.** If a webhook is missed, `GET /payments/{uuid}?refresh=1`
  pulls the state from the gateway and applies it through the same state
  machine.
- **Turning a gateway off** stops it being offered, but does not stop it
  settling: webhooks and status lookups still resolve the implementation. A
  customer mid-payment is never stranded.
- **Ledger.** `transactions` is append-only: `charge` on success, a `charge` row
  with `status: failed` on a declined attempt, `refund` on a refund. The
  reconciler never updates an existing row.
- **Order status.** Success confirms a pending order (`confirmed_at` set);
  refund marks it `refunded`; failure leaves it pending so the customer can pay
  another way. COD captures at delivery via `OrderService::updateStatus`.

## Tests

`api/tests/Feature/Payment/` — 34 cases:

- `PaymentMethodsTest` — availability rules, master switch, currency filtering,
  missing driver class, disabled-but-settleable.
- `SafepayGatewayTest` — session + checkout URL, request shape (minor units,
  public key, secret header), metadata, retry reusing the payment, refunds.
- `SafepayWebhookTest` — signature accept/reject, idempotency, out-of-order
  events, tracker-only matching, unattributable events, unknown gateway.
- `PaymentReconcilerTest` — transitions, ledger, no double-posting.

```bash
docker exec htashop-api php artisan test tests/Feature/Payment
```

No test reaches the network: `Http::preventStrayRequests()` is set in
`beforeEach` and every gateway call is faked.
