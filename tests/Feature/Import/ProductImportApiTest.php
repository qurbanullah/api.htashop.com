<?php

namespace Tests\Feature\Import;

use App\Enums\ProductImportStatus;
use App\Http\Middleware\ApiAuthenticate;
use App\Jobs\Import\ApplyProductImportChunkJob;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Import\ProductImportService;
use App\Support\Import\ProductImportColumns;
use Database\Seeders\MeasurementSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Storage::disk('local')->deleteDirectory('imports');

    config()->set('auth.guards.api', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    $this->withoutMiddleware(ApiAuthenticate::class);

    $this->tenant = Tenant::create([
        'name' => 'HTAShop',
        'slug' => 'htashop',
        'is_active' => true,
    ]);

    $this->organization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'HTAShop',
        'slug' => 'htashop',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $this->user = User::factory()->create();

    Membership::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'user_id' => $this->user->id,
        'role' => 'owner',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $this->warehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Main Warehouse',
        'code' => 'MAIN',
        'is_active' => true,
    ]);

    $this->seed(MeasurementSeeder::class);

    $this->electronics = Category::query()->create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'is_active' => true,
    ]);

    Category::query()->create([
        'name' => 'Sensors & Transducers',
        'slug' => 'sensors-transducers',
        'parent_id' => $this->electronics->id,
        'is_active' => true,
    ]);

    Brand::query()->create([
        'name' => 'Arduino',
        'slug' => 'arduino',
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'is_approved' => true,
        'is_active' => true,
    ]);

    $this->actingAs($this->user, 'api');
});

afterEach(function (): void {
    Storage::disk('local')->deleteDirectory('imports');
});

/**
 * @param  array<int, array<string, string>>  $rows
 */
function apiImportCsvContent(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    fputcsv($handle, ProductImportColumns::PRODUCTS);

    foreach ($rows as $row) {
        fputcsv($handle, array_map(
            fn (string $column): string => (string) ($row[$column] ?? ''),
            ProductImportColumns::PRODUCTS,
        ));
    }

    rewind($handle);
    $content = (string) stream_get_contents($handle);
    fclose($handle);

    return $content;
}

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function apiImportRow(array $overrides = []): array
{
    return array_merge([
        'external_id' => 'HTA-ARDUINO-UNO',
        'name' => 'Arduino UNO R3',
        'category_path' => 'Electronics > Sensors & Transducers',
        'brand' => 'Arduino',
        'status' => 'active',
        'currency' => 'PKR',
        'price' => '1199',
        'cost_price' => '900',
        'stock_qty' => '50',
        'warehouse_code' => 'MAIN',
        'features' => 'ATmega328P|14 digital I/O',
        'specs' => 'Microcontroller=ATmega328P',
        'tags' => 'arduino|dev-board',
    ], $overrides);
}

/**
 * @param  array<int, array<string, string>>  $rows
 */
function apiImportUpload(array $rows, array $options = []): UploadedFile
{
    return UploadedFile::fake()->createWithContent('import.csv', apiImportCsvContent($rows));
}

test('an upload stages the rows and reports the validation summary', function () {
    $response = $this->postJson('/api/v1/product-imports', [
        'file' => apiImportUpload([
            apiImportRow(),
            apiImportRow(['external_id' => '', 'name' => 'Missing external id']),
        ]),
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', ProductImportStatus::VALIDATED)
        ->assertJsonPath('data.kind', 'products')
        ->assertJsonPath('data.counts.total', 2)
        ->assertJsonPath('data.counts.valid', 1)
        ->assertJsonPath('data.counts.invalid', 1);

    $import = ProductImport::query()->firstOrFail();

    expect($import->user_id)->toBe($this->user->id)
        ->and($import->tenant_id)->toBe($this->tenant->id)
        ->and($import->organization_id)->toBe($this->organization->id)
        ->and($import->original_filename)->toBe('import.csv');

    Storage::disk('local')->assertExists($import->path);

    // Validation alone writes no catalogue records.
    expect(Product::query()->count())->toBe(0);
});

test('committing an import queues the chunk job which then applies the rows', function () {
    Queue::fake();

    $this->postJson('/api/v1/product-imports', [
        'file' => apiImportUpload([apiImportRow()]),
    ])->assertCreated()
        ->assertJsonPath('data.status', ProductImportStatus::VALIDATED);

    $import = ProductImport::query()->firstOrFail();

    $this->postJson("/api/v1/product-imports/{$import->uuid}/commit")
        ->assertStatus(202)
        ->assertJsonPath('data.status', ProductImportStatus::IMPORTING);

    Queue::assertPushed(
        ApplyProductImportChunkJob::class,
        fn (ApplyProductImportChunkJob $job): bool => $job->importId === $import->id && $job->afterRowId === 0,
    );

    (new ApplyProductImportChunkJob($import->id))->handle(app(ProductImportService::class));

    $import->refresh();

    expect($import->status)->toBe(ProductImportStatus::COMPLETED)
        ->and($import->created_count)->toBe(1)
        ->and($import->progressPercentage())->toBe(100);

    $product = Product::query()->where('seller_sku', 'HTA-ARDUINO-UNO')->firstOrFail();

    expect((float) $product->cost_price)->toBe(900.0)
        ->and((float) $product->inventories()->firstOrFail()->quantity)->toBe(50.0);

    // Nothing staged is left, so a second commit is a client error rather than
    // a silent no-op.
    $this->postJson("/api/v1/product-imports/{$import->uuid}/commit")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('a cancelled import is not applied by an in-flight chunk job', function () {
    Queue::fake();

    $this->postJson('/api/v1/product-imports', [
        'file' => apiImportUpload([apiImportRow()]),
    ])->assertCreated();

    $import = ProductImport::query()->firstOrFail();

    $this->postJson("/api/v1/product-imports/{$import->uuid}/commit")->assertStatus(202);

    $this->postJson("/api/v1/product-imports/{$import->uuid}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', ProductImportStatus::CANCELLED);

    (new ApplyProductImportChunkJob($import->id))->handle(app(ProductImportService::class));

    expect(Product::query()->count())->toBe(0)
        ->and($import->refresh()->status)->toBe(ProductImportStatus::CANCELLED);

    $this->postJson("/api/v1/product-imports/{$import->uuid}/commit")->assertStatus(409);
});

test('a dry run upload can be committed afterwards', function () {
    Queue::fake();

    $this->postJson('/api/v1/product-imports', [
        'file' => apiImportUpload([apiImportRow()]),
        'dry_run' => true,
    ])->assertCreated()
        ->assertJsonPath('data.status', ProductImportStatus::COMPLETED)
        ->assertJsonPath('data.counts.valid', 1);

    $import = ProductImport::query()->firstOrFail();

    $this->postJson("/api/v1/product-imports/{$import->uuid}/commit")->assertStatus(202);
});

test('the rows endpoint paginates and filters by status', function () {
    $this->postJson('/api/v1/product-imports', [
        'file' => apiImportUpload([
            apiImportRow(),
            apiImportRow(['external_id' => 'HTA-BROKEN', 'price' => 'free']),
        ]),
    ])->assertCreated();

    $import = ProductImport::query()->firstOrFail();

    $this->getJson("/api/v1/product-imports/{$import->uuid}/rows")
        ->assertOk()
        ->assertJsonPath('data.meta.total', 2);

    $this->getJson("/api/v1/product-imports/{$import->uuid}/rows?status=invalid")
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.data.0.external_id', 'HTA-BROKEN')
        ->assertJsonPath('data.data.0.errors.price', 'price must be a number.');
});

test('rejected rows can be downloaded as a csv', function () {
    $this->postJson('/api/v1/product-imports', [
        'file' => apiImportUpload([
            apiImportRow(),
            apiImportRow(['external_id' => 'HTA-BROKEN', 'price' => 'free']),
        ]),
    ])->assertCreated();

    $import = ProductImport::query()->firstOrFail();

    $response = $this->get("/api/v1/product-imports/{$import->uuid}/errors.csv");

    $response->assertOk();

    $body = $response->streamedContent();

    expect($body)->toContain('row_number,external_id,status,errors')
        ->and($body)->toContain('HTA-BROKEN')
        ->and($body)->not->toContain('HTA-ARDUINO-UNO');
});

test('imports are scoped to the organizations the user belongs to', function () {
    $foreignTenant = Tenant::create(['name' => 'Other', 'slug' => 'other', 'is_active' => true]);
    $foreignOrganization = Organization::create([
        'tenant_id' => $foreignTenant->id,
        'name' => 'Other',
        'slug' => 'other',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $own = ProductImport::query()->create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'user_id' => $this->user->id,
        'original_filename' => 'own.csv',
        'path' => 'imports/own.csv',
        'format' => 'csv',
        'status' => ProductImportStatus::VALIDATED,
    ]);

    $foreign = ProductImport::query()->create([
        'tenant_id' => $foreignTenant->id,
        'organization_id' => $foreignOrganization->id,
        'original_filename' => 'foreign.csv',
        'path' => 'imports/foreign.csv',
        'format' => 'csv',
        'status' => ProductImportStatus::VALIDATED,
    ]);

    $this->getJson('/api/v1/product-imports')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.data.0.uuid', $own->uuid);

    $this->getJson("/api/v1/product-imports/{$own->uuid}")->assertOk();
    $this->getJson("/api/v1/product-imports/{$foreign->uuid}")->assertForbidden();
});

test('an admin without a membership must name the tenant and organization explicitly', function () {
    $admin = User::factory()->create();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
    $admin->assignRole('admin');

    $this->actingAs($admin, 'api');

    $this->postJson('/api/v1/product-imports', [
        'file' => apiImportUpload([apiImportRow()]),
    ])->assertStatus(422);

    $this->postJson('/api/v1/product-imports', [
        'file' => apiImportUpload([apiImportRow()]),
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
    ])->assertCreated();
});

test('an admin who belongs to an organization is scoped to it without extra input', function () {
    $admin = User::factory()->create();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
    $admin->assignRole('admin');

    Membership::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'user_id' => $admin->id,
        'role' => 'owner',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $this->actingAs($admin, 'api');

    $this->postJson('/api/v1/product-imports', [
        'file' => apiImportUpload([apiImportRow()]),
    ])->assertCreated()
        ->assertJsonPath('data.organization_id', $this->organization->id);
});

test('only csv, xlsx and ods files are accepted', function () {
    $this->postJson('/api/v1/product-imports', [
        'file' => UploadedFile::fake()->createWithContent('import.pdf', '%PDF-1.4'),
    ])->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

test('only the documented import kinds are accepted', function () {
    $this->postJson('/api/v1/product-imports', [
        'file' => apiImportUpload([apiImportRow()]),
        'kind' => 'orders',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('kind');
});

test('a commit with no valid rows reports a client error', function () {
    $this->postJson('/api/v1/product-imports', [
        'file' => apiImportUpload([
            apiImportRow(['external_id' => '', 'name' => '']),
        ]),
    ])->assertCreated();

    $import = ProductImport::query()->firstOrFail();

    $this->postJson("/api/v1/product-imports/{$import->uuid}/commit")
        ->assertStatus(422)
        ->assertJsonPath('message', 'There are no valid rows left to import. Fix the file and upload it again.');
});
