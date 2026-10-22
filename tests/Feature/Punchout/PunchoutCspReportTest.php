<?php

use Illuminate\Support\Facades\Log;

it('records a legacy csp-report from the punchout origin', function () {
    Log::spy();

    $report = [
        'csp-report' => [
            // A violation during handoff must not leak the one-time code.
            'document-uri' => 'https://punchout.htashop.com/session?code=one-time-secret',
            'violated-directive' => 'frame-ancestors',
            'blocked-uri' => 'https://buyer.example',
            'source-file' => 'https://punchout.htashop.com/assets/app.js',
            'line-number' => 12,
            'disposition' => 'enforce',
        ],
    ];

    $this->withHeaders([
        'Content-Type' => 'application/csp-report',
        'Origin' => 'https://punchout.htashop.com',
    ])->postJson('/api/v1/punchout/csp-report', $report)
        ->assertNoContent();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'punchout.csp_violation'
            && $context['document_uri'] === 'https://punchout.htashop.com/session'
            && $context['violated_directive'] === 'frame-ancestors'
            && $context['blocked_uri'] === 'https://buyer.example')
        ->once();
});

it('records Reports API entries and ignores other documents', function () {
    Log::spy();

    $reports = [
        [
            'type' => 'csp-violation',
            'body' => [
                'documentURL' => 'https://punchout.htashop.com/cart',
                'effectiveDirective' => 'img-src',
                'blockedURL' => 'https://untrusted.example/pixel.png',
            ],
        ],
        [
            'type' => 'csp-violation',
            'body' => [
                'documentURL' => 'https://storefront.example/',
                'effectiveDirective' => 'img-src',
            ],
        ],
    ];

    $this->postJson('/api/v1/punchout/csp-report', $reports, ['Content-Type' => 'application/reports+json'])
        ->assertNoContent();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'punchout.csp_violation'
            && $context['document_uri'] === 'https://punchout.htashop.com/cart'
            && $context['violated_directive'] === 'img-src')
        ->once();
});

it('silently discards malformed reports', function () {
    Log::spy();

    $this->call('POST', '/api/v1/punchout/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], 'not json')
        ->assertNoContent();

    Log::shouldNotHaveReceived('warning');
});
