<?php

namespace Tests\Feature\Quote;

use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    ensurePersonalAccessClient();
});

function reportTenant(): Tenant
{
    return Tenant::query()->create([
        'name' => 'Report Tenant',
        'slug' => 'rp-tenant-'.Str::lower(Str::random(8)),
        'is_active' => true,
    ]);
}

function reportOrganization(Tenant $tenant, string $name): Organization
{
    return Organization::query()->create([
        'tenant_id' => $tenant->id,
        'name' => $name,
        'slug' => 'rp-org-'.Str::lower(Str::random(8)),
        'type' => 'vendor',
        'is_active' => true,
    ]);
}

function reportQuote(array $attributes = []): QuoteRequest
{
    return QuoteRequest::query()->create(array_merge([
        'first_name' => 'Nadia',
        'last_name' => 'Iqbal',
        'email' => 'nadia@example.test',
        'organization' => 'Buyer Co',
        'job_title' => 'Buyer',
        'country' => 'Pakistan',
        'city' => 'Lahore',
        'postal_code' => '54000',
        'message' => 'Need pricing.',
        'status' => 'pending',
        'requirements' => ['product_name' => 'Sensor'],
    ], $attributes));
}

function reportResponse(QuoteRequest $quote, array $attributes = []): QuoteResponse
{
    return QuoteResponse::query()->create(array_merge([
        'quote_request_id' => $quote->id,
        'subject' => 'Offer',
        'message' => 'Pricing enclosed.',
        'currency' => 'PKR',
        'validity_days' => 30,
        'revision' => 1,
        'status' => 'sent',
        'sent_at' => now(),
        'expires_at' => now()->addDays(30),
        'subtotal' => 5000,
        'total_amount' => 5000,
    ], $attributes));
}

function reportAdmin(): User
{
    $role = Role::findOrCreate('admin', 'api');
    $admin = User::factory()->create();
    $admin->assignRole($role);

    return $admin;
}

describe('quote report', function () {
    it('summarises the platform for admins', function () {
        $tenant = reportTenant();
        $organization = reportOrganization($tenant, 'Acme');

        $accepted = reportQuote([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'status' => 'accepted',
            'accepted_at' => now(),
            'first_response_at' => now()->addMinutes(30),
        ]);
        reportResponse($accepted, ['status' => 'accepted', 'total_amount' => 7500]);

        $pending = reportQuote([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
        ]);
        reportResponse($pending);

        $admin = reportAdmin();

        $response = $this->withToken(signInDevice($admin->email)['access_token'])
            ->getJson('/api/v1/admin/quotes/report?days=90')
            ->assertOk();

        expect($response->json('data.kpis.total'))->toBe(2)
            ->and($response->json('data.kpis.accepted'))->toBe(1)
            ->and($response->json('data.kpis.open'))->toBe(1)
            ->and((float) $response->json('data.kpis.win_rate'))->toBe(100.0)
            ->and($response->json('data.kpis.accepted_value_by_currency.PKR'))->toEqual(7500)
            ->and($response->json('data.trend'))->toHaveCount(12)
            ->and($response->json('data.by_status'))->toHaveCount(7)
            ->and($response->json('data.top_organizations.0.name'))->toBe('Acme');
    });

    it('forbids non-admin callers', function () {
        $user = User::factory()->create();

        $this->withToken(signInDevice($user->email)['access_token'])
            ->getJson('/api/v1/admin/quotes/report')
            ->assertForbidden();
    });

    it('lists only accounts that have quotes', function () {
        $tenant = reportTenant();
        $used = reportOrganization($tenant, 'Used Org');
        $unused = reportOrganization($tenant, 'Unused Org');

        reportQuote([
            'tenant_id' => $tenant->id,
            'organization_id' => $used->id,
        ]);

        $admin = reportAdmin();

        $response = $this->withToken(signInDevice($admin->email)['access_token'])
            ->getJson('/api/v1/admin/quotes/accounts')
            ->assertOk();

        expect(collect($response->json('data.organizations'))->pluck('name')->all())
            ->toBe(['Used Org'])
            ->not->toContain($unused->name);
    });
});

describe('admin quote list filters', function () {
    it('filters by organization for admins', function () {
        $tenant = reportTenant();
        $first = reportOrganization($tenant, 'First');
        $second = reportOrganization($tenant, 'Second');

        reportQuote(['tenant_id' => $tenant->id, 'organization_id' => $first->id]);
        reportQuote(['tenant_id' => $tenant->id, 'organization_id' => $second->id]);

        $admin = reportAdmin();

        $response = $this->withToken(signInDevice($admin->email)['access_token'])
            ->getJson('/api/v1/quotes?organization_id='.$second->id)
            ->assertOk();

        expect($response->json('data.data'))->toHaveCount(1)
            ->and($response->json('data.data.0.organization_ref.id'))->toBe($second->id);
    });
});
