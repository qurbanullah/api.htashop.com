<?php

use App\Models\Tenant;
use App\Services\Punchout\PunchoutProtocolService;

it('resolves the configured default punchout protocol', function () {
    config()->set('punchout.default_protocol', 'cxml');
    config()->set('punchout.supported_protocols', ['cxml', 'oci']);

    $tenant = new Tenant([
        'uuid' => 'tenant-default-uuid',
        'name' => 'Acme Tenant',
        'slug' => 'acme-tenant',
        'settings' => [],
    ]);

    $service = app(PunchoutProtocolService::class);

    expect($service->resolve($tenant)->protocol())->toBe('cxml');
    expect($service->configuration($tenant)['default_protocol'])->toBe('cxml');
});

it('resolves the tenant default punchout protocol when supported', function () {
    config()->set('punchout.default_protocol', 'cxml');
    config()->set('punchout.supported_protocols', ['cxml', 'oci']);

    $tenant = new Tenant([
        'uuid' => 'tenant-oci-uuid',
        'name' => 'OCI Tenant',
        'slug' => 'oci-tenant',
        'settings' => [
            'punchout' => [
                'default_protocol' => 'oci',
                'supported_protocols' => ['oci', 'cxml'],
            ],
        ],
    ]);

    $service = app(PunchoutProtocolService::class);

    expect($service->resolve($tenant)->protocol())->toBe('oci');
    expect($service->configuration($tenant)['default_protocol'])->toBe('oci');
});

it('falls back to the first supported protocol when tenant default is invalid', function () {
    config()->set('punchout.default_protocol', 'cxml');
    config()->set('punchout.supported_protocols', ['cxml', 'oci']);

    $tenant = new Tenant([
        'uuid' => 'tenant-invalid-uuid',
        'name' => 'Fallback Tenant',
        'slug' => 'fallback-tenant',
        'settings' => [
            'punchout' => [
                'default_protocol' => 'bad-protocol',
                'supported_protocols' => ['oci'],
            ],
        ],
    ]);

    $service = app(PunchoutProtocolService::class);

    expect($service->configuration($tenant)['default_protocol'])->toBe('oci');
    expect($service->resolve($tenant)->protocol())->toBe('oci');
});

it('builds protocol specific setup and cart contexts', function () {
    config()->set('punchout.default_protocol', 'cxml');
    config()->set('punchout.supported_protocols', ['cxml', 'oci']);
    config()->set('punchout.protocols.oci.mode', 'form');

    $tenant = new Tenant([
        'uuid' => 'tenant-context-uuid',
        'name' => 'Context Tenant',
        'slug' => 'context-tenant',
        'settings' => [
            'punchout' => [
                'default_protocol' => 'oci',
                'supported_protocols' => ['oci', 'cxml'],
                'protocols' => [
                    'oci' => [
                        'cart_operation' => 'CUSTOM_BACKGROUND_POST',
                    ],
                ],
            ],
        ],
    ]);

    $service = app(PunchoutProtocolService::class);

    $setupContext = $service->buildSetupContext($tenant);
    $cartContext = $service->buildCartContext($tenant);

    expect($setupContext['protocol'])->toBe('oci');
    expect($setupContext['operation'])->toBe('OCI_LOGIN');
    expect($setupContext['tenant_slug'])->toBe('context-tenant');
    expect($cartContext['protocol'])->toBe('oci');
    expect($cartContext['operation'])->toBe('CUSTOM_BACKGROUND_POST');
    expect($cartContext['mode'])->toBe('form');
});
