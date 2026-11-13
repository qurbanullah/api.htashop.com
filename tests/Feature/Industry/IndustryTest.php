<?php

namespace Tests\Feature\Industry;

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Industry;
use App\Models\Manufacturer;
use App\Models\QuoteRequest;
use App\Models\User;
use Database\Seeders\IndustrySeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'api');
    $this->withoutMiddleware(ApiAuthenticate::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('seeds the full industry taxonomy idempotently', function () {
    $this->seed(IndustrySeeder::class);
    $this->seed(IndustrySeeder::class);

    expect(Industry::query()->count())->toBe(147);
    expect(Industry::query()->where('code', 'information-technology-services')->value('name'))
        ->toBe('Information Technology & Services');
    expect(Industry::query()->where('code', 'recreational-facilities-services')->exists())
        ->toBeTrue();
    expect(Industry::query()->where('code', 'airlines-aviation')->exists())->toBeTrue();
    expect(Industry::query()->where('code', 'primary-secondary')->exists())->toBeTrue();
});

it('classifies categories, manufacturers and their brands', function () {
    $category = Category::factory()->create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'parent_id' => null,
        'is_active' => true,
    ]);

    $manufacturer = Manufacturer::create([
        'name' => 'TSMC',
        'slug' => 'tsmc',
        'type' => 'manufacturer',
        'is_active' => true,
    ]);

    $brand = Brand::create([
        'name' => 'TSMC',
        'slug' => 'tsmc-brand',
        'manufacturer_id' => $manufacturer->id,
        'is_active' => true,
    ]);

    $this->seed(IndustrySeeder::class);

    expect($category->fresh()->industries->pluck('code')->all())
        ->toContain('consumer-electronics', 'semiconductors', 'electrical-electronic-manufacturing');

    expect($manufacturer->fresh()->industries->pluck('code')->all())
        ->toContain('semiconductors');

    // Brands inherit their manufacturer's industries.
    expect($brand->fresh()->industries->pluck('code')->all())
        ->toContain('semiconductors');
});

it('exposes the active taxonomy publicly', function () {
    $this->seed(IndustrySeeder::class);

    $response = $this->getJson('/api/v1/industries')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(147);
    expect($response->json('data.0.name'))->toBe('Accounting');
});

it('lets an admin create, update and delete an industry', function () {
    $created = $this->actingAs($this->admin, 'api')
        ->postJson('/api/v1/admin/industries', [
            'name' => 'Space Mining',
            'sort_order' => 999,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'space-mining');

    $code = $created->json('data.code');

    $this->putJson('/api/v1/admin/industries/'.$code, [
        'name' => 'Space Mining & Refining',
        'is_active' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Space Mining & Refining')
        ->assertJsonPath('data.is_active', false);

    $this->deleteJson('/api/v1/admin/industries/'.$code)->assertOk();

    expect(Industry::query()->where('code', $code)->exists())->toBeFalse();
});

it('forbids non-admins from managing industries', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/admin/industries', ['name' => 'Shadow Industry'])
        ->assertForbidden();
});

it('stores a structured industry on quote requests and syncs the legacy label', function () {
    $this->seed(IndustrySeeder::class);

    $industry = Industry::query()->where('code', 'information-technology-services')->firstOrFail();

    $payload = [
        'first_name' => 'Ahmed',
        'last_name' => 'Khan',
        'email' => 'ahmed@example.test',
        'organization' => 'AgriTech Labs',
        'job_title' => 'Engineer',
        'country' => 'Pakistan',
        'city' => 'Lahore',
        'postal_code' => '54000',
        'message' => 'Need a quote for fifty drone motors.',
    ];

    $this->postJson('/api/v1/quotes', $payload + ['industry_id' => $industry->id])
        ->assertStatus(201);

    $request = QuoteRequest::query()->latest('id')->firstOrFail();

    expect($request->industry_id)->toBe($industry->id)
        ->and($request->industry)->toBe('Information Technology & Services');
});

it('resolves a legacy free-text industry to the catalogue', function () {
    $this->seed(IndustrySeeder::class);

    $payload = [
        'first_name' => 'Sara',
        'last_name' => 'Ali',
        'email' => 'sara@example.test',
        'organization' => 'Ledger Co',
        'job_title' => 'Analyst',
        'country' => 'Pakistan',
        'city' => 'Karachi',
        'postal_code' => '74000',
        'message' => 'Please quote twenty barcode scanners.',
        'industry' => 'Banking',
    ];

    $this->postJson('/api/v1/quotes', $payload)->assertStatus(201);

    $request = QuoteRequest::query()->latest('id')->firstOrFail();

    expect($request->industry_id)->not->toBeNull()
        ->and($request->industryModel?->code)->toBe('banking');
});
