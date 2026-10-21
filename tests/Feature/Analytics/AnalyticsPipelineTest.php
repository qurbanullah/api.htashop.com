<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;
use App\Support\Analytics\ClientContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function analyticsTenant(): Tenant
{
    return Tenant::query()->create([
        'name' => 'Analytics Tenant',
        'slug' => 'analytics-'.Str::lower(Str::random(8)),
        'is_active' => true,
    ]);
}

function analyticsProduct(Tenant $tenant): Product
{
    $organization = Organization::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Analytics Org',
        'slug' => 'analytics-org-'.Str::lower(Str::random(8)),
        'type' => 'vendor',
    ]);

    return Product::query()->create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'name' => 'Analytics Widget',
        'slug' => 'analytics-widget-'.Str::lower(Str::random(8)),
        'status' => 'published',
        'is_active' => true,
    ]);
}

it('records a direct event into the buffer', function (): void {
    $tenant = analyticsTenant();
    $product = analyticsProduct($tenant);
    $buffer = app(\App\Contracts\Analytics\AnalyticsBuffer::class);

    $recorded = app(\App\Services\Analytics\AnalyticsService::class)->record(
        \App\Support\Analytics\EventEnvelope::fromArray([
            'event_name' => 'product_view',
            'occurred_at' => now()->toIso8601String(),
            'source' => 'web',
            'session_id' => 'sess-direct',
            'tenant_id' => $tenant->id,
            'context' => [
                'product' => ['id' => $product->id, 'name' => $product->name],
                'consent' => ['analytics' => true],
            ],
        ]),
    );

    expect($recorded)->toBeTrue()
        ->and($buffer->pending())->toBe(2);
});

it('executes a dispatched RecordEvent synchronously', function (): void {
    $tenant = analyticsTenant();
    $product = analyticsProduct($tenant);
    $buffer = app(\App\Contracts\Analytics\AnalyticsBuffer::class);

    $job = new \App\Jobs\Analytics\RecordEvent([
        'event_name' => 'product_view',
        'occurred_at' => now()->toIso8601String(),
        'source' => 'web',
        'session_id' => 'sess-job',
        'tenant_id' => $tenant->id,
        'context' => [
            'product' => ['id' => $product->id, 'name' => $product->name],
            'consent' => ['analytics' => true],
        ],
    ]);

    expect($job->afterCommit)->toBeNull();

    \App\Jobs\Analytics\RecordEvent::dispatch($job->event);

    expect($buffer->pending())->toBe(2);
});

it('dispatches a RecordEvent job for a consented beacon', function (): void {
    $tenant = analyticsTenant();
    $product = analyticsProduct($tenant);

    \Illuminate\Support\Facades\Queue::fake();

    expect(config('queue.default'))->toBe('sync');

    $this->postJson('/api/v1/analytics/events', [
        'events' => [[
            'event_name' => 'product_view',
            'session_id' => 'sess-fake-queue',
            'context' => [
                'product' => ['id' => $product->id],
                'consent' => ['analytics' => true],
            ],
        ]],
    ])->assertStatus(202);

    \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\Analytics\RecordEvent::class);
});

it('accepts, dedupes and flushes a consented product view', function (): void {
    $tenant = analyticsTenant();
    $product = analyticsProduct($tenant);

    $event = [
        'event_name' => 'product_view',
        'occurred_at' => now()->toIso8601String(),
        'session_id' => 'sess-analytics-1',
        'context' => [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => 1250,
                'currency' => 'PKR',
            ],
            'consent' => ['analytics' => true],
        ],
    ];

    $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/120.0 Mobile Safari/537.36')
        ->postJson('/api/v1/analytics/events', ['events' => [$event, $event]])
        ->assertStatus(202)
        ->assertJsonPath('data.accepted', 2);

    expect(app(\App\Contracts\Analytics\AnalyticsBuffer::class)->pending())->toBe(2);

    $this->artisan('analytics:flush')->assertExitCode(0);

    $row = DB::table('analytics_daily')
        ->where('entity_type', 'product')
        ->where('entity_id', (string) $product->id)
        ->where('metric_name', 'view')
        ->where('metric_date', now()->toDateString())
        ->first();

    expect($row)->not->toBeNull()
        ->and((int) $row->count)->toBe(1)
        ->and($product->fresh()->view_count)->toBe(1);
});

it('drops browser events without analytics consent', function (): void {
    $tenant = analyticsTenant();
    $product = analyticsProduct($tenant);

    $this->postJson('/api/v1/analytics/events', [
        'events' => [[
            'event_name' => 'product_view',
            'session_id' => 'sess-no-consent',
            'context' => [
                'product' => ['id' => $product->id],
                'consent' => ['analytics' => false],
            ],
        ]],
    ])->assertStatus(202)
        ->assertJsonPath('data.accepted', 0)
        ->assertJsonPath('data.dropped', 1);

    $this->artisan('analytics:flush')->assertExitCode(0);

    expect(DB::table('analytics_daily')->count())->toBe(0)
        ->and($product->fresh()->view_count)->toBe(0);
});

it('does not count bot traffic', function (): void {
    $tenant = analyticsTenant();
    $product = analyticsProduct($tenant);

    $this->withHeader('User-Agent', 'Googlebot/2.1 (+http://www.google.com/bot.html)')
        ->postJson('/api/v1/analytics/events', [
            'events' => [[
                'event_name' => 'product_view',
                'session_id' => 'sess-bot',
                'context' => [
                    'product' => ['id' => $product->id],
                    'consent' => ['analytics' => true],
                ],
            ]],
        ])
        ->assertStatus(202)
        ->assertJsonPath('data.accepted', 1);

    $this->artisan('analytics:flush')->assertExitCode(0);

    expect(DB::table('analytics_daily')->count())->toBe(0)
        ->and($product->fresh()->view_count)->toBe(0);
});

it('does not let a browser claim api source to bypass consent', function (): void {
    $tenant = analyticsTenant();
    $product = analyticsProduct($tenant);

    $this->postJson('/api/v1/analytics/events', [
        'events' => [[
            'event_name' => 'product_view',
            'source' => 'api',
            'session_id' => 'sess-source-spoof',
            'context' => [
                'product' => ['id' => $product->id],
                'consent' => ['analytics' => false],
            ],
        ]],
    ])->assertStatus(202)
        ->assertJsonPath('data.accepted', 0)
        ->assertJsonPath('data.dropped', 1);

    $this->artisan('analytics:flush')->assertExitCode(0);

    expect(DB::table('analytics_daily')->count())->toBe(0);
});

it('rejects browser-fabricated purchase events', function (): void {
    $this->postJson('/api/v1/analytics/events', [
        'events' => [[
            'event_name' => 'purchase',
            'session_id' => 'sess-fake',
            'context' => [
                'order' => ['id' => 'fake-order', 'value' => 999999],
                'consent' => ['analytics' => true],
            ],
        ]],
    ])->assertStatus(202)
        ->assertJsonPath('data.accepted', 0)
        ->assertJsonPath('data.dropped', 1);

    expect(DB::table('analytics_daily')->count())->toBe(0);
});

it('emits a server-side purchase and reconciles it with the order', function (): void {
    $tenant = analyticsTenant();
    $product = analyticsProduct($tenant);

    $order = Order::query()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'ORD-ANALYTICS-1',
        'status' => OrderStatus::PENDING,
        'customer_name' => 'Analytics Buyer',
        'customer_email' => 'buyer@example.test',
        'currency' => 'PKR',
        'subtotal' => 2500,
        'total_amount' => 2500,
        'placed_at' => now(),
        'metadata' => ['payment_method' => 'cod'],
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'name' => $product->name,
        'sku' => 'ANALYTICS-SKU',
        'quantity' => 2,
        'unit_price' => 1250,
        'total' => 2500,
        'currency' => 'PKR',
    ]);

    $order->update(['status' => OrderStatus::CONFIRMED]);

    $this->artisan('analytics:flush')->assertExitCode(0);

    $storePurchase = DB::table('analytics_daily')
        ->where('entity_type', 'store')
        ->where('metric_name', 'purchase')
        ->where('source', 'server')
        ->where('metric_date', now()->toDateString())
        ->first();

    $productPurchase = DB::table('analytics_daily')
        ->where('entity_type', 'product')
        ->where('entity_id', (string) $product->id)
        ->where('metric_name', 'purchase')
        ->where('source', 'server')
        ->where('metric_date', now()->toDateString())
        ->first();

    expect($storePurchase)->not->toBeNull()
        ->and((int) $storePurchase->count)->toBe(1)
        ->and((float) $storePurchase->value_sum)->toBe(2500.0)
        ->and($productPurchase)->not->toBeNull()
        ->and((int) $productPurchase->count)->toBe(2)
        ->and($product->fresh()->purchase_count)->toBe(2);

    $this->artisan('analytics:reconcile', ['--date' => now()->toDateString()])
        ->assertExitCode(0);
});

it('classifies devices and bots from the user agent', function (): void {
    $mobile = ClientContext::fromUserAgent('Mozilla/5.0 (Linux; Android 14) Chrome/120.0 Mobile Safari/537.36');
    $desktop = ClientContext::fromUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36');
    $bot = ClientContext::fromUserAgent('Googlebot/2.1 (+http://www.google.com/bot.html)');

    expect($mobile->deviceType)->toBe('mobile')
        ->and($mobile->browser)->toBe('Chrome')
        ->and($desktop->deviceType)->toBe('desktop')
        ->and($desktop->platform)->toBe('Windows')
        ->and($bot->isBot)->toBeTrue()
        ->and($bot->deviceType)->toBe('bot');
});
