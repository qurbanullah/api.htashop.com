<?php

namespace Tests\Feature\Punchout;

use App\Http\Middleware\ApiAuthenticate;
use App\Models\BuyerItem;
use App\Models\BuyerItemMapping;
use App\Models\Membership;
use App\Models\User;
use App\Services\Punchout\BuyerItemMappingService;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'api');

    config()->set('auth.guards.api', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    $this->withoutMiddleware(ApiAuthenticate::class);
});

function importAdmin($test): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $test->actingAs($admin, 'api');

    return $admin;
}

function importCsv(array $lines): UploadedFile
{
    $header = 'buyer_part_number,our_sku,variant_sku,short_description,long_description,unit_of_measure,manufacturer_part_number,gtin,is_primary,effective_from,effective_to';

    return UploadedFile::fake()->createWithContent(
        'buyer-parts.csv',
        $header."\n".implode("\n", $lines)."\n",
    );
}

it('previews an import without writing any mapping', function () {
    importAdmin($this);

    $tenant = punchoutTenant('import-preview-tenant');
    $organization = punchoutOrganization($tenant, 'import-preview-org');
    $first = punchoutProduct($tenant, $organization, 'Import Preview One', ['punchout']);
    $first->update(['sku' => 'SKU-1', 'gtin' => '8901234567890']);
    $second = punchoutProduct($tenant, $organization, 'Import Preview Two', ['punchout']);
    $second->update(['sku' => 'SKU-2']);

    $buyer = punchoutBuyer($tenant);

    app(BuyerItemMappingService::class)->map($buyer, [
        'buyer_part_number' => 'CONFLICT-1',
        'product_id' => $second->id,
    ]);

    $file = importCsv([
        '1000048217,SKU-1,,MPU6050 MODULE,Six axis motion,EA,MPU-6050,,true,,',
        'CONFLICT-1,SKU-1,,,,,,,,',
        'NOMATCH-1,UNKNOWN-SKU,,,,,,,,',
        '1000048217,SKU-1,,,,,,,,',
        ',SKU-1,,,,,,,,',
        'SUGGEST-1,,,GTIN SUGGESTED,,,,8901234567890,true,,',
    ]);

    $response = $this->post('/api/v1/admin/punchout/buyers/'.$buyer->uuid.'/part-numbers/import', [
        'file' => $file,
    ])->assertOk();

    expect($response->json('data.dry_run'))->toBeTrue();
    expect($response->json('data.totals.rows'))->toBe(6);
    expect($response->json('data.totals.create'))->toBe(1);
    expect($response->json('data.totals.conflict'))->toBe(1);
    expect($response->json('data.totals.unmatched'))->toBe(1);
    expect($response->json('data.totals.duplicate'))->toBe(1);
    expect($response->json('data.totals.invalid'))->toBe(1);
    expect($response->json('data.totals.suggested'))->toBe(1);
    expect($response->json('data.totals.applied'))->toBe(0);

    // Only the mapping created by the fixture exists.
    expect(BuyerItemMapping::query()->count())->toBe(1);
    expect(BuyerItem::query()->count())->toBe(1);
});

it('applies an import and re-runs as unchanged', function () {
    importAdmin($this);

    $tenant = punchoutTenant('import-apply-tenant');
    $organization = punchoutOrganization($tenant, 'import-apply-org');
    $first = punchoutProduct($tenant, $organization, 'Import Apply One', ['punchout']);
    $first->update(['sku' => 'SKU-A']);
    $second = punchoutProduct($tenant, $organization, 'Import Apply Two', ['punchout']);
    $second->update(['sku' => 'SKU-B']);

    $buyer = punchoutBuyer($tenant);

    $apply = $this->post('/api/v1/admin/punchout/buyers/'.$buyer->uuid.'/part-numbers/import', [
        'file' => importCsv([
            'CODE-A,SKU-A,,Short A,Long A,EA,MPN-A,,true,,',
            'CODE-B,SKU-B,,,,,,,,',
        ]),
        'dry_run' => false,
    ])->assertOk();

    expect($apply->json('data.dry_run'))->toBeFalse();
    expect($apply->json('data.totals.applied'))->toBe(2);
    expect($apply->json('data.totals.create'))->toBe(2);
    expect(BuyerItem::query()->count())->toBe(2);
    expect(BuyerItemMapping::query()->count())->toBe(2);

    $item = BuyerItem::query()->where('code', 'CODE-A')->firstOrFail();
    expect($item->short_description)->toBe('Short A');
    expect($item->long_description)->toBe('Long A');

    $rerun = $this->post('/api/v1/admin/punchout/buyers/'.$buyer->uuid.'/part-numbers/import', [
        'file' => importCsv([
            'CODE-A,SKU-A,,,,,,,,',
            'CODE-B,SKU-B,,,,,,,,',
        ]),
    ])->assertOk();

    expect($rerun->json('data.totals.unchanged'))->toBe(2);
    expect($rerun->json('data.totals.create'))->toBe(0);
    expect(BuyerItemMapping::query()->count())->toBe(2);
});

it('reports conflicts and only remaps when asked', function () {
    importAdmin($this);

    $tenant = punchoutTenant('import-conflict-tenant');
    $organization = punchoutOrganization($tenant, 'import-conflict-org');
    $first = punchoutProduct($tenant, $organization, 'Import Conflict One', ['punchout']);
    $first->update(['sku' => 'SKU-X']);
    $second = punchoutProduct($tenant, $organization, 'Import Conflict Two', ['punchout']);
    $second->update(['sku' => 'SKU-Y']);
    $buyer = punchoutBuyer($tenant);

    app(BuyerItemMappingService::class)->map($buyer, [
        'buyer_part_number' => 'MOVE-1',
        'product_id' => $second->id,
    ]);

    $file = fn () => importCsv(['MOVE-1,SKU-X,,,,,,,,']);

    $conflict = $this->post('/api/v1/admin/punchout/buyers/'.$buyer->uuid.'/part-numbers/import', [
        'file' => $file(),
    ])->assertOk();

    expect($conflict->json('data.totals.conflict'))->toBe(1);

    $remap = $this->post('/api/v1/admin/punchout/buyers/'.$buyer->uuid.'/part-numbers/import', [
        'file' => $file(),
        'dry_run' => false,
        'remap' => true,
    ])->assertOk();

    expect($remap->json('data.totals.applied'))->toBe(1);
    expect($remap->json('data.totals.remap'))->toBe(1);

    expect(BuyerItemMapping::query()->first()?->product_id)->toBe($first->id);
});

it('suggests and optionally applies a GTIN match', function () {
    importAdmin($this);

    $tenant = punchoutTenant('import-suggest-tenant');
    $organization = punchoutOrganization($tenant, 'import-suggest-org');
    $product = punchoutProduct($tenant, $organization, 'Import Suggest Product', ['punchout']);
    $product->update(['gtin' => '8900000000017']);
    $buyer = punchoutBuyer($tenant);

    $file = fn () => importCsv(['SUGGEST-9,,,GTIN ITEM,,,,8900000000017,true,,']);

    $preview = $this->post('/api/v1/admin/punchout/buyers/'.$buyer->uuid.'/part-numbers/import', [
        'file' => $file(),
    ])->assertOk();

    expect($preview->json('data.totals.suggested'))->toBe(1);
    expect($preview->json('data.rows.0.suggested_by'))->toBe('gtin');
    expect(BuyerItemMapping::query()->count())->toBe(0);

    $applied = $this->post('/api/v1/admin/punchout/buyers/'.$buyer->uuid.'/part-numbers/import', [
        'file' => $file(),
        'dry_run' => false,
        'apply_suggestions' => true,
    ])->assertOk();

    expect($applied->json('data.totals.applied'))->toBe(1);
    expect(BuyerItemMapping::query()->first()?->product_id)->toBe($product->id);
});

it('downloads the import template', function () {
    importAdmin($this);

    $tenant = punchoutTenant('import-template-tenant');
    $buyer = punchoutBuyer($tenant);

    $response = $this->get('/api/v1/admin/punchout/buyers/'.$buyer->uuid.'/part-numbers/template.csv');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->getContent())->toContain('buyer_part_number,our_sku,variant_sku');
});

it('scopes manager imports to the caller tenant', function () {
    $tenant = punchoutTenant('import-manager-tenant');
    $otherTenant = punchoutTenant('import-manager-other');
    $organization = punchoutOrganization($tenant, 'import-manager-org');
    $product = punchoutProduct($tenant, $organization, 'Managed Import Product', ['punchout']);
    $product->update(['sku' => 'SKU-M']);
    $buyer = punchoutBuyer($tenant);
    $foreign = punchoutBuyer($otherTenant);

    $user = User::factory()->create();
    $managerOrganization = punchoutOrganization($tenant, 'import-manager-membership-org');
    Membership::query()->create([
        'tenant_id' => $tenant->id,
        'organization_id' => $managerOrganization->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_primary' => true,
        'is_active' => true,
    ]);
    $this->actingAs($user, 'api');

    $this->post('/api/v1/manage/punchout/buyers/'.$buyer->uuid.'/part-numbers/import', [
        'file' => importCsv(['CODE-M,SKU-M,,,,,,,,']),
        'dry_run' => false,
    ])->assertOk();

    expect(BuyerItemMapping::query()->count())->toBe(1);

    $this->post('/api/v1/manage/punchout/buyers/'.$foreign->uuid.'/part-numbers/import', [
        'file' => importCsv(['CODE-X,SKU-M,,,,,,,,']),
        'dry_run' => false,
    ])->assertNotFound();
});
