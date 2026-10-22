<?php

use App\Actions\Punchout\IssueHandoffCodeAction;

it('scopes the punchout catalog to the session tenant and channel', function () {
    $tenantA = punchoutTenant('isolation-tenant-a');
    $organizationA = punchoutOrganization($tenantA, 'isolation-org-a');
    $tenantB = punchoutTenant('isolation-tenant-b');
    $organizationB = punchoutOrganization($tenantB, 'isolation-org-b');

    punchoutProduct($tenantA, $organizationA, 'Punchout Only', ['punchout']);
    punchoutProduct($tenantA, $organizationA, 'Storefront Only', ['storefront']);
    punchoutProduct($tenantA, $organizationA, 'Both Channels', ['storefront', 'punchout']);
    $otherTenantProduct = punchoutProduct($tenantB, $organizationB, 'Other Tenant Punchout', ['punchout']);

    $session = punchoutSession($tenantA);
    $code = app(IssueHandoffCodeAction::class)->handle($session);
    $token = (string) $this->postJson('/api/v1/punchout/sessions/exchange', ['code' => $code])
        ->json('data.session.access_token');

    $response = $this->withHeader('Authorization', 'Punchout '.$token)
        ->getJson('/api/v1/punchout/'.$tenantA->uuid.'/catalog/products')
        ->assertOk();

    $names = collect($response->json('data.data'))->pluck('name')->all();

    expect($names)->toContain('Punchout Only', 'Both Channels');
    expect($names)->not->toContain('Storefront Only', 'Other Tenant Punchout');

    $this->withHeader('Authorization', 'Punchout '.$token)
        ->getJson('/api/v1/punchout/'.$tenantA->uuid.'/catalog/products/'.$otherTenantProduct->uuid)
        ->assertNotFound();
});

it('keeps punchout-only products out of the public storefront catalog', function () {
    $tenant = punchoutTenant('storefront-scope-tenant');
    $organization = punchoutOrganization($tenant, 'storefront-scope-org');

    punchoutProduct($tenant, $organization, 'Public Punchout Only', ['punchout']);
    punchoutProduct($tenant, $organization, 'Public Storefront Only', ['storefront']);
    punchoutProduct($tenant, $organization, 'Public Both Channels', ['storefront', 'punchout']);

    $response = $this->getJson('/api/v1/catalog/products')->assertOk();

    $names = collect($response->json('data.data'))->pluck('name')->all();

    expect($names)->toContain('Public Storefront Only', 'Public Both Channels');
    expect($names)->not->toContain('Public Punchout Only');
});

it('ignores a punchout channel request on the public catalog', function () {
    $tenant = punchoutTenant('public-channel-tenant');
    $organization = punchoutOrganization($tenant, 'public-channel-org');

    punchoutProduct($tenant, $organization, 'Public Punchout Hidden', ['punchout']);
    punchoutProduct($tenant, $organization, 'Public Storefront Visible', ['storefront']);

    $response = $this->getJson('/api/v1/catalog/products?channel=punchout')->assertOk();

    $names = collect($response->json('data.data'))->pluck('name')->all();

    expect($names)->toContain('Public Storefront Visible');
    expect($names)->not->toContain('Public Punchout Hidden');
});

it('exposes channel memberships on the product resource', function () {
    $tenant = punchoutTenant('resource-channel-tenant');
    $organization = punchoutOrganization($tenant, 'resource-channel-org');
    $product = punchoutProduct($tenant, $organization, 'Channel Resource Product', ['storefront', 'punchout']);

    $response = $this->getJson('/api/v1/catalog/products/'.$product->uuid)->assertOk();

    expect($response->json('data.channels'))->toContain('storefront', 'punchout');
});
