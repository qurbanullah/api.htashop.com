<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes prometheus business metrics', function (): void {
    $response = $this->get('/metrics');

    $response->assertOk();

    $body = (string) $response->getContent();

    expect($body)->toContain('htashop_up 1')
        ->and($body)->toContain('htashop_orders_today')
        ->and($body)->toContain('htashop_revenue_today_pkr')
        ->and($body)->toContain('htashop_low_stock_skus')
        ->and($body)->toContain('htashop_analytics_pending_counters')
        ->and($body)->toContain('htashop_audit_export_last_success_timestamp_seconds');
});

it('requires the configured bearer token', function (): void {
    config(['observability.metrics.token' => 'metrics-secret']);

    $this->get('/metrics')->assertForbidden();

    $this->withHeader('Authorization', 'Bearer metrics-secret')
        ->get('/metrics')
        ->assertOk();
});
