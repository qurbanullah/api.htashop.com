<?php

namespace Tests\Feature\Quote;

use App\Models\Measurement;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductShipping;
use App\Models\QuoteActivity;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Quotes\QuoteService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    ensurePersonalAccessClient();
    Mail::fake();
});

function mqTenant(string $name = 'Tenant'): Tenant
{
    return Tenant::query()->create([
        'name' => $name,
        'slug' => 'mq-tenant-'.Str::lower(Str::random(8)),
        'is_active' => true,
    ]);
}

function mqOrganization(Tenant $tenant, string $name = 'Organization'): Organization
{
    return Organization::query()->create([
        'tenant_id' => $tenant->id,
        'name' => $name,
        'slug' => 'mq-org-'.Str::lower(Str::random(8)),
        'type' => 'vendor',
        'is_active' => true,
    ]);
}

function mqMember(User $user, Tenant $tenant, Organization $organization): Membership
{
    return Membership::query()->create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_primary' => true,
        'is_active' => true,
    ]);
}

function mqQuote(array $attributes = []): QuoteRequest
{
    return QuoteRequest::query()->create(array_merge([
        'first_name' => 'Sara',
        'last_name' => 'Ali',
        'email' => 'sara@example.test',
        'organization' => 'Buyer Co',
        'job_title' => 'Engineer',
        'country' => 'Pakistan',
        'city' => 'Lahore',
        'postal_code' => '54000',
        'message' => 'Please quote the items below.',
        'status' => 'pending',
        'requirements' => [
            'product_name' => 'Stepper Motor',
            'sku' => 'MOT-17',
            'quantity' => 10,
        ],
    ], $attributes));
}

function mqReply(array $overrides = []): array
{
    return array_merge([
        'subject' => 'Pricing for your request',
        'message' => 'Please find our offer below.',
        'validity_days' => 30,
        'items' => [[
            'name' => 'Stepper Motor NEMA 17',
            'sku' => 'MOT-17',
            'quantity' => 10,
            'unit_price' => 1500,
            'discount_amount' => 0,
            'tax_rate' => 0,
        ]],
    ], $overrides);
}

function mqSignIn(User $user): string
{
    return signInDevice($user->email)['access_token'];
}

describe('quote list', function () {
    it('requires authentication', function () {
        $this->getJson('/api/v1/quotes')->assertUnauthorized();
    });

    it('returns only quotes belonging to the caller organization plus counts', function () {
        $tenant = mqTenant();
        $mine = mqOrganization($tenant, 'Mine');
        $theirs = mqOrganization($tenant, 'Theirs');

        $merchant = User::factory()->create();
        mqMember($merchant, $tenant, $mine);

        mqQuote(['tenant_id' => $tenant->id, 'organization_id' => $mine->id]);
        mqQuote(['tenant_id' => $tenant->id, 'organization_id' => $theirs->id]);

        $this->withToken(mqSignIn($merchant))
            ->getJson('/api/v1/quotes')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.counts.all', 1)
            ->assertJsonPath('data.counts.pending', 1);
    });

    it('lets admins see every quote', function () {
        $tenant = mqTenant();
        $organization = mqOrganization($tenant);

        mqQuote(['tenant_id' => $tenant->id, 'organization_id' => $organization->id]);
        mqQuote();

        $role = Role::findOrCreate('admin', 'api');
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $this->withToken(mqSignIn($admin))
            ->getJson('/api/v1/quotes')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 2);
    });

    it('filters by search across requirement fields', function () {
        $tenant = mqTenant();
        $organization = mqOrganization($tenant);

        $merchant = User::factory()->create();
        mqMember($merchant, $tenant, $organization);

        mqQuote(['tenant_id' => $tenant->id, 'organization_id' => $organization->id]);
        mqQuote([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'requirements' => ['product_name' => 'Hydraulic Pump', 'sku' => 'HYD-99'],
        ]);

        $this->withToken(mqSignIn($merchant))
            ->getJson('/api/v1/quotes?search=HYD-99')
            ->assertOk()
            ->assertJsonCount(1, 'data.data');
    });
});

describe('quote detail', function () {
    it('hides a quote from another organization behind a 404', function () {
        $tenant = mqTenant();
        $mine = mqOrganization($tenant, 'Mine');
        $theirs = mqOrganization($tenant, 'Theirs');

        $merchant = User::factory()->create();
        mqMember($merchant, $tenant, $mine);

        $foreign = mqQuote(['tenant_id' => $tenant->id, 'organization_id' => $theirs->id]);

        $this->withToken(mqSignIn($merchant))
            ->getJson('/api/v1/quotes/'.$foreign->uuid)
            ->assertNotFound();
    });

    it('prefills packaging from the quoted product', function () {
        $tenant = mqTenant();
        $organization = mqOrganization($tenant);

        $merchant = User::factory()->create();
        mqMember($merchant, $tenant, $organization);

        $weight = Measurement::create([
            'tenant_id' => $tenant->id,
            'name' => 'Weight',
            'code' => 'weight',
            'is_active' => true,
        ]);
        $kilogram = Unit::create([
            'tenant_id' => $tenant->id,
            'measurement_id' => $weight->id,
            'name' => 'Kilogram',
            'code' => 'kg',
            'symbol' => 'kg',
            'factor' => 1,
            'offset' => 0,
            'precision' => 3,
            'is_active' => true,
        ]);

        $length = Measurement::create([
            'tenant_id' => $tenant->id,
            'name' => 'Length',
            'code' => 'length',
            'is_active' => true,
        ]);
        $centimetre = Unit::create([
            'tenant_id' => $tenant->id,
            'measurement_id' => $length->id,
            'name' => 'Centimetre',
            'code' => 'cm',
            'symbol' => 'cm',
            'factor' => 0.01,
            'offset' => 0,
            'precision' => 2,
            'is_active' => true,
        ]);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'name' => 'Stepper Motor',
            'slug' => 'stepper-motor',
            'status' => 'active',
            'is_active' => true,
        ]);

        ProductShipping::create([
            'product_id' => $product->id,
            'gross_weight' => 12.5,
            'net_weight' => 11,
            'weight_unit_id' => $kilogram->id,
            'length' => 40,
            'width' => 30,
            'height' => 20,
            'dimension_unit_id' => $centimetre->id,
            'units_per_case' => 6,
            'is_fragile' => true,
            'handling_notes' => 'Keep dry',
        ]);

        $quote = mqQuote([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'quotable_type' => Product::class,
            'quotable_id' => $product->id,
        ]);

        $response = $this->withToken(mqSignIn($merchant))
            ->getJson('/api/v1/quotes/'.$quote->uuid)
            ->assertOk();

        expect((float) $response->json('data.packaging_prefill.gross_weight_kg'))->toBe(12.5)
            ->and((float) $response->json('data.packaging_prefill.net_weight_kg'))->toBe(11.0)
            ->and($response->json('data.packaging_prefill.dimensions'))->toBe('40 × 30 × 20')
            ->and($response->json('data.packaging_prefill.units_per_case'))->toBe(6)
            ->and($response->json('data.packaging_prefill.is_fragile'))->toBeTrue()
            ->and($response->json('data.packaging_prefill.handling_notes'))->toBe('Keep dry');
    });

    it('returns the full detail payload', function () {
        $tenant = mqTenant();
        $organization = mqOrganization($tenant);

        $merchant = User::factory()->create();
        mqMember($merchant, $tenant, $organization);

        $quote = mqQuote([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'job_title' => 'Procurement Lead',
        ]);

        $this->withToken(mqSignIn($merchant))
            ->getJson('/api/v1/quotes/'.$quote->uuid)
            ->assertOk()
            ->assertJsonPath('data.quote_number', $quote->quote_number)
            ->assertJsonPath('data.job_title', 'Procurement Lead')
            ->assertJsonPath('data.requirements.sku', 'MOT-17');
    });
});

describe('replying to a quote', function () {
    it('creates a sent revision, marks the quote replied and computes totals', function () {
        $tenant = mqTenant();
        $organization = mqOrganization($tenant);

        $merchant = User::factory()->create();
        mqMember($merchant, $tenant, $organization);

        $quote = mqQuote(['tenant_id' => $tenant->id, 'organization_id' => $organization->id]);

        $response = $this->withToken(mqSignIn($merchant))
            ->postJson('/api/v1/quotes/'.$quote->uuid.'/responses', mqReply())
            ->assertCreated();

        expect($response->json('data.status'))->toBe('sent')
            ->and((float) $response->json('data.subtotal'))->toBe(15000.0)
            ->and((float) $response->json('data.total_amount'))->toBe(15000.0);

        $quote->refresh();
        expect($quote->status)->toBe('replied');

        expect(QuoteActivity::query()
            ->where('quote_request_id', $quote->id)
            ->where('type', QuoteActivity::RESPONSE_SENT)
            ->exists())->toBeTrue();
    });

    it('saves a draft without marking the quote replied', function () {
        $tenant = mqTenant();
        $organization = mqOrganization($tenant);

        $merchant = User::factory()->create();
        mqMember($merchant, $tenant, $organization);

        $quote = mqQuote(['tenant_id' => $tenant->id, 'organization_id' => $organization->id]);

        $this->withToken(mqSignIn($merchant))
            ->postJson('/api/v1/quotes/'.$quote->uuid.'/responses', mqReply(['send' => false]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        expect($quote->fresh()->status)->toBe('pending');
    });

    it('supersedes the previous revision when a second reply is sent', function () {
        $tenant = mqTenant();
        $organization = mqOrganization($tenant);

        $merchant = User::factory()->create();
        mqMember($merchant, $tenant, $organization);

        $quote = mqQuote(['tenant_id' => $tenant->id, 'organization_id' => $organization->id]);
        $token = mqSignIn($merchant);

        $first = $this->withToken($token)
            ->postJson('/api/v1/quotes/'.$quote->uuid.'/responses', mqReply())
            ->assertCreated();

        $this->withToken($token)
            ->postJson('/api/v1/quotes/'.$quote->uuid.'/responses', mqReply(['subject' => 'Revised offer']))
            ->assertCreated()
            ->assertJsonPath('data.revision', 2);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/quotes/'.$quote->uuid)
            ->assertOk()
            ->assertJsonPath('data.responses.1.uuid', $first->json('data.uuid'))
            ->assertJsonPath('data.responses.1.status', 'superseded');
    });

    it('sends a saved draft later', function () {
        $tenant = mqTenant();
        $organization = mqOrganization($tenant);

        $merchant = User::factory()->create();
        mqMember($merchant, $tenant, $organization);

        $quote = mqQuote(['tenant_id' => $tenant->id, 'organization_id' => $organization->id]);
        $token = mqSignIn($merchant);

        $draftUuid = $this->withToken($token)
            ->postJson('/api/v1/quotes/'.$quote->uuid.'/responses', mqReply(['send' => false]))
            ->assertCreated()
            ->json('data.uuid');

        expect($quote->fresh()->status)->toBe('pending');

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->postJson('/api/v1/quotes/'.$quote->uuid.'/responses/'.$draftUuid.'/send')
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');

        expect($quote->fresh()->status)->toBe('replied');
    });

    it('stores commercial and logistics terms with the revision', function () {
        $tenant = mqTenant();
        $organization = mqOrganization($tenant);

        $merchant = User::factory()->create();
        mqMember($merchant, $tenant, $organization);

        $quote = mqQuote(['tenant_id' => $tenant->id, 'organization_id' => $organization->id]);

        $this->withToken(mqSignIn($merchant))
            ->postJson('/api/v1/quotes/'.$quote->uuid.'/responses', mqReply([
                'incoterm' => 'CIF',
                'incoterm_location' => 'Karachi Port',
                'payment_terms' => 'net_30',
                'warranty_months' => 12,
                'shipping_method' => 'sea_freight',
                'country_of_origin' => 'CN',
                'hs_code' => '8542.31',
                'lead_time_days' => 21,
                'estimated_ship_date' => now()->addDays(30)->toDateString(),
                'port_of_loading' => 'Shenzhen',
                'port_of_discharge' => 'Karachi',
                'partial_shipment_allowed' => false,
                'package_count' => 4,
                'gross_weight_kg' => 12.5,
                'net_weight_kg' => 11,
                'package_dimensions' => '40 x 30 x 20 cm',
                'shipping_notes' => 'Fragile',
                'packaging_notes' => 'Anti-static bags',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.incoterm', 'CIF')
            ->assertJsonPath('data.incoterm_label', 'CIF — Cost, Insurance and Freight')
            ->assertJsonPath('data.payment_terms_label', 'Net 30 days')
            ->assertJsonPath('data.shipping_method_label', 'Sea freight')
            ->assertJsonPath('data.partial_shipment_allowed', false);

        $revision = QuoteResponse::query()->where('quote_request_id', $quote->id)->firstOrFail();

        expect($revision->incoterm_location)->toBe('Karachi Port')
            ->and($revision->package_dimensions)->toBe('40 x 30 x 20 cm')
            ->and((float) $revision->gross_weight_kg)->toBe(12.5);
    });

    it('rejects an unknown incoterm', function () {
        $tenant = mqTenant();
        $organization = mqOrganization($tenant);

        $merchant = User::factory()->create();
        mqMember($merchant, $tenant, $organization);

        $quote = mqQuote(['tenant_id' => $tenant->id, 'organization_id' => $organization->id]);

        $this->withToken(mqSignIn($merchant))
            ->postJson('/api/v1/quotes/'.$quote->uuid.'/responses', mqReply(['incoterm' => 'XYZ']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('incoterm');
    });

    it('rejects a reply with no line items', function () {
        $tenant = mqTenant();
        $organization = mqOrganization($tenant);

        $merchant = User::factory()->create();
        mqMember($merchant, $tenant, $organization);

        $quote = mqQuote(['tenant_id' => $tenant->id, 'organization_id' => $organization->id]);

        $this->withToken(mqSignIn($merchant))
            ->postJson('/api/v1/quotes/'.$quote->uuid.'/responses', mqReply(['items' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');
    });
});

describe('quote workflow', function () {
    it('moves a pending quote to in progress and back', function () {
        $tenant = mqTenant();
        $organization = mqOrganization($tenant);

        $merchant = User::factory()->create();
        mqMember($merchant, $tenant, $organization);

        $quote = mqQuote(['tenant_id' => $tenant->id, 'organization_id' => $organization->id]);
        $token = mqSignIn($merchant);

        $this->withToken($token)
            ->putJson('/api/v1/quotes/'.$quote->uuid.'/status', ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->putJson('/api/v1/quotes/'.$quote->uuid.'/status', ['status' => 'cancelled'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    });

    it('refuses an invalid status transition', function () {
        $tenant = mqTenant();
        $organization = mqOrganization($tenant);

        $merchant = User::factory()->create();
        mqMember($merchant, $tenant, $organization);

        $quote = mqQuote(['tenant_id' => $tenant->id, 'organization_id' => $organization->id]);

        $this->withToken(mqSignIn($merchant))
            ->putJson('/api/v1/quotes/'.$quote->uuid.'/status', ['status' => 'accepted'])
            ->assertStatus(422);
    });

    it('assigns a quote and appends a note', function () {
        $tenant = mqTenant();
        $organization = mqOrganization($tenant);

        $merchant = User::factory()->create();
        mqMember($merchant, $tenant, $organization);

        $quote = mqQuote(['tenant_id' => $tenant->id, 'organization_id' => $organization->id]);
        $token = mqSignIn($merchant);

        $this->withToken($token)
            ->postJson('/api/v1/quotes/'.$quote->uuid.'/assign', ['user_id' => $merchant->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_to.id', $merchant->id)
            ->assertJsonPath('data.status', 'in_progress');

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->postJson('/api/v1/quotes/'.$quote->uuid.'/notes', ['body' => 'Called the buyer.'])
            ->assertCreated()
            ->assertJsonPath('data.type', QuoteActivity::NOTE);

        expect(QuoteActivity::query()
            ->where('quote_request_id', $quote->id)
            ->where('type', QuoteActivity::NOTE)
            ->count())->toBe(1);
    });
});

describe('buyer decision', function () {
    it('accepts the latest sent revision', function () {
        $tenant = mqTenant();
        $organization = mqOrganization($tenant);

        $merchant = User::factory()->create();
        mqMember($merchant, $tenant, $organization);

        $quote = mqQuote(['tenant_id' => $tenant->id, 'organization_id' => $organization->id]);

        $this->withToken(mqSignIn($merchant))
            ->postJson('/api/v1/quotes/'.$quote->uuid.'/responses', mqReply())
            ->assertCreated();

        $this->postJson(URL::temporarySignedRoute('quotes.accept', now()->addHour(), ['uuid' => $quote->uuid]))
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        expect($quote->fresh()->status)->toBe('accepted')
            ->and($quote->fresh()->accepted_at)->not->toBeNull();
    });

    it('declines with a reason', function () {
        $tenant = mqTenant();
        $organization = mqOrganization($tenant);

        $merchant = User::factory()->create();
        mqMember($merchant, $tenant, $organization);

        $quote = mqQuote(['tenant_id' => $tenant->id, 'organization_id' => $organization->id]);

        $this->withToken(mqSignIn($merchant))
            ->postJson('/api/v1/quotes/'.$quote->uuid.'/responses', mqReply())
            ->assertCreated();

        $this->postJson(
            URL::temporarySignedRoute('quotes.decline', now()->addHour(), ['uuid' => $quote->uuid]),
            ['reason' => 'Budget too high'],
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'declined');

        expect($quote->fresh()->status)->toBe('declined');
    });

    it('refuses to accept an expired offer', function () {
        $tenant = mqTenant();
        $organization = mqOrganization($tenant);

        $merchant = User::factory()->create();
        mqMember($merchant, $tenant, $organization);

        $quote = mqQuote(['tenant_id' => $tenant->id, 'organization_id' => $organization->id]);

        $this->withToken(mqSignIn($merchant))
            ->postJson('/api/v1/quotes/'.$quote->uuid.'/responses', mqReply())
            ->assertCreated();

        app(QuoteService::class)->loadDetail($quote)->latestResponse
            ->update(['expires_at' => now()->subDay()]);

        $this->postJson(URL::temporarySignedRoute('quotes.accept', now()->addHour(), ['uuid' => $quote->uuid]))
            ->assertStatus(422);

        expect($quote->fresh()->status)->toBe('expired');
    });
});
