<?php

use App\Actions\Punchout\IssueHandoffCodeAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Gateways\CodGateway;
use App\Gateways\PaymentGatewayManager;
use App\Gateways\Safepay\SafepayClient;
use App\Gateways\SafepayGateway;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Price;
use App\Models\Product;
use App\Models\PunchoutBuyer;
use App\Models\PunchoutSession;
use App\Models\SalesChannel;
use App\Models\Tenant;
use App\Services\Payment\PaymentReconciler;
use App\Services\Payment\PaymentService;
use App\Support\Ai\ChatIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class, RefreshDatabase::class)->in('Unit');

/**
 * Fake the configured chat provider with a deterministic streamed reply, so
 * tests never reach DeepSeek.
 */
function fakeChatStream(string $text = 'Hello there'): void
{
    config([
        'ai.default' => 'deepseek',
        'ai.providers.deepseek.api_key' => 'test-key',
        'ai.providers.deepseek.base_url' => 'https://api.deepseek.com',
        'ai.providers.deepseek.model' => 'deepseek-chat',
    ]);

    $frame = fn (array $delta, ?string $finish = null) => 'data: '.json_encode([
        'choices' => [['delta' => $delta, 'finish_reason' => $finish]],
    ]);

    $body = implode("\n\n", [
        $frame(['content' => $text]),
        $frame([], 'stop'),
        'data: '.json_encode(['choices' => [], 'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 3]]),
        'data: [DONE]',
    ])."\n\n";

    Http::fake(['*' => Http::response($body, 200)]);
}

/**
 * The visitor token issued by a chat response, for reuse in a follow-up request
 * (Laravel's test client does not carry cookies between calls).
 */
function chatVisitorToken(TestResponse $response): string
{
    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === ChatIdentity::COOKIE);

    return (string) ($cookie?->getValue() ?? '');
}

/**
 * Signing in mints a Passport access token, which needs a personal-access client.
 * A freshly migrated test database has none (production gets one from
 * `passport:install`), so create one on demand.
 */
function ensurePersonalAccessClient(): void
{
    $exists = Client::query()
        ->where('revoked', false)
        ->get()
        ->contains(fn ($client): bool => $client->hasGrantType('personal_access'));

    if ($exists) {
        return;
    }

    Client::create([
        'name' => 'Test Personal Access Client',
        'secret' => null,
        'provider' => 'users',
        'redirect_uris' => [],
        'grant_types' => ['personal_access'],
        'revoked' => false,
    ]);
}

/**
 * Signs a device in the way the native app does and returns its token pair.
 *
 * @return array{access_token: string, refresh_token: string}
 */
function signInDevice(string $email, string $password = 'password'): array
{
    ensurePersonalAccessClient();

    $response = test()
        ->withHeader('X-Client', 'native')
        ->postJson('/api/v1/login', ['email' => $email, 'password' => $password])
        ->assertOk();

    return [
        'access_token' => (string) $response->json('data.access_token'),
        'refresh_token' => (string) $response->json('data.refresh_token'),
    ];
}

/**
 * Point the Safepay gateway at known test credentials.
 *
 * Gateway bindings are singletons built from config, so they have to be
 * forgotten for the new values to take effect.
 */
function configureSafepay(array $overrides = []): void
{
    config([
        'payment.enabled' => true,
        'payment.gateways.safepay' => array_merge([
            'driver' => SafepayGateway::class,
            'enabled' => true,
            'environment' => 'sandbox',
            'public_key' => 'sec_test_public_key',
            'secret_key' => 'test_secret_key',
            'webhook_secret' => 'test_webhook_secret',
            'intent' => 'CYBERSOURCE',
            'source' => 'hosted',
            'currencies' => ['PKR', 'USD'],
            'timeout' => 5,
            'retries' => 0,
            'api_base' => null,
            'checkout_base' => null,
        ], $overrides),
    ]);

    forgetPaymentGateways();
}

/**
 * Drop the cached payment bindings and the manager's resolved-gateway cache.
 */
function forgetPaymentGateways(): void
{
    foreach ([
        SafepayClient::class,
        SafepayGateway::class,
        PaymentGatewayManager::class,
        CodGateway::class,
        PaymentReconciler::class,
        PaymentService::class,
    ] as $abstract) {
        app()->forgetInstance($abstract);
    }
}

function paymentTenant(): Tenant
{
    return Tenant::query()->create([
        'name' => 'HTAShop',
        'slug' => 'htashop-'.Str::lower(Str::random(8)),
        'is_active' => true,
    ]);
}

function paymentOrder(array $attributes = []): Order
{
    return Order::query()->create(array_merge([
        'tenant_id' => paymentTenant()->id,
        'order_number' => 'ORD-'.Str::upper(Str::random(10)),
        'status' => OrderStatus::PENDING,
        'total_amount' => 1500.00,
        'currency' => 'PKR',
    ], $attributes));
}

function paymentRecord(Order $order, array $attributes = []): Payment
{
    return Payment::query()->create(array_merge([
        'order_id' => $order->id,
        'payment_method' => PaymentMethod::COD,
        'status' => PaymentStatus::PENDING,
        'amount' => $order->total_amount,
        'currency' => $order->currency,
    ], $attributes));
}

function safepaySignature(string $body, string $secret = 'test_webhook_secret'): string
{
    return hash_hmac('sha512', $body, $secret);
}

function punchoutTenant(string $slug): Tenant
{
    return Tenant::create([
        'name' => Str::headline($slug),
        'slug' => $slug,
        'is_active' => true,
    ]);
}

function punchoutOrganization(Tenant $tenant, string $slug): Organization
{
    return Organization::create([
        'tenant_id' => $tenant->id,
        'name' => Str::headline($slug),
        'slug' => $slug,
        'type' => 'vendor',
        'is_active' => true,
    ]);
}

function punchoutProduct(Tenant $tenant, Organization $organization, string $name, array $channels, array $metadata = []): Product
{
    $product = Product::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'name' => $name,
        'slug' => Str::slug($name),
        'status' => 'active',
        'is_active' => true,
        'metadata' => array_merge(['price' => 10.00, 'currency' => 'USD'], $metadata),
    ]);

    $ids = SalesChannel::query()->whereIn('code', $channels)->pluck('id')->all();
    $product->salesChannels()->sync($ids);

    return $product->load('salesChannels');
}

function punchoutSession(Tenant $tenant, string $protocol = 'cxml', string $returnUrl = 'https://buyer.example/return', ?PunchoutBuyer $buyer = null): PunchoutSession
{
    return PunchoutSession::create([
        'tenant_id' => $tenant->id,
        'buyer_id' => $buyer?->id,
        'protocol' => $protocol,
        'status' => 'started',
        'buyer_cookie' => 'buyer-cookie',
        'return_url' => $returnUrl,
        'expires_at' => now()->addMinutes(30),
        'idle_expires_at' => now()->addMinutes(15),
        'started_at' => now(),
        'last_activity_at' => now(),
    ]);
}

function punchoutAccessToken($test, PunchoutSession $session): string
{
    $code = app(IssueHandoffCodeAction::class)->handle($session);

    return (string) $test->postJson('/api/v1/punchout/sessions/exchange', ['code' => $code])
        ->json('data.session.access_token');
}

function punchoutBuyer(Tenant $tenant, array $attributes = []): PunchoutBuyer
{
    return PunchoutBuyer::create(array_merge([
        'tenant_id' => $tenant->id,
        'name' => 'Buyer Co',
        'code' => 'buyer-'.Str::lower(Str::random(8)),
        'protocol' => 'cxml',
        'sender_identity' => 'buyer-sender',
        'from_identity' => 'buyer-from',
        'to_identity' => 'htashop',
        'shared_secret_hash' => PunchoutBuyer::hashSecret('buyer-shared-secret'),
        'is_active' => true,
    ], $attributes));
}

function punchoutBuyerOrganization(Tenant $tenant, string $slug): Organization
{
    return Organization::create([
        'tenant_id' => $tenant->id,
        'name' => Str::headline($slug),
        'slug' => $slug,
        'type' => 'buyer',
        'is_active' => true,
    ]);
}

function punchoutCurrency(string $code = 'USD'): Currency
{
    return Currency::query()->firstOrCreate(
        ['code' => $code],
        ['name' => $code, 'symbol' => $code === 'USD' ? '$' : $code, 'is_active' => true],
    );
}

function punchoutPrice(Tenant $tenant, Organization $organization, Product $product, array $attributes = []): Price
{
    return Price::query()->create(array_merge([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'currency_id' => punchoutCurrency()->id,
        'priceable_type' => Product::class,
        'priceable_id' => $product->id,
        'type' => 'fixed',
        'base_price' => 10.00,
        'min_quantity' => 1,
        'priority' => 0,
        'is_active' => true,
    ], $attributes));
}
