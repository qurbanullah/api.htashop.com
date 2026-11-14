<?php

namespace Tests\Feature\Import;

use App\Enums\ProductImportRowStatus;
use App\Enums\ProductImportStatus;
use App\Jobs\Import\ApplyProductImportChunkJob;
use App\Models\Organization;
use App\Models\ProductImport;
use App\Models\ProductImportRow;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
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
});

/**
 * An import left at "importing" by a worker that stopped mid-run.
 *
 * `updated_at` moves through the query builder because a model update would
 * refresh the staleness clock this test is built around.
 */
function recoveryImport(Tenant $tenant, Organization $organization, int $staleMinutes = 30): ProductImport
{
    $import = ProductImport::query()->create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'original_filename' => 'products.csv',
        'path' => 'imports/products.csv',
        'format' => 'csv',
        'status' => ProductImportStatus::IMPORTING,
        'total_rows' => 2,
        'processed_rows' => 1,
        'started_at' => now()->subHour(),
    ]);

    DB::table('product_imports')
        ->where('id', $import->id)
        ->update(['updated_at' => now()->subMinutes($staleMinutes)]);

    return $import->refresh();
}

function recoveryRow(ProductImport $import, int $rowNumber, string $status): ProductImportRow
{
    return ProductImportRow::query()->create([
        'import_id' => $import->id,
        'row_number' => $rowNumber,
        'external_id' => 'HTA-ROW-'.$rowNumber,
        'raw' => ['external_id' => 'HTA-ROW-'.$rowNumber],
        'normalized' => ['external_id' => 'HTA-ROW-'.$rowNumber],
        'status' => $status,
    ]);
}

test('a stalled import is resumed after the last applied row', function () {
    Queue::fake();

    $import = recoveryImport($this->tenant, $this->organization);
    $applied = recoveryRow($import, 1, ProductImportRowStatus::IMPORTED);
    recoveryRow($import, 2, ProductImportRowStatus::VALID);

    $this->artisan('products:imports:recover')->assertExitCode(0);

    Queue::assertPushed(
        ApplyProductImportChunkJob::class,
        fn (ApplyProductImportChunkJob $job): bool => $job->importId === $import->id
            && $job->afterRowId === (int) $applied->id,
    );

    expect($import->refresh()->updated_at->greaterThan(now()->subMinutes(30)))->toBeTrue();
});

test('an import that is still making progress is left alone', function () {
    Queue::fake();

    $import = recoveryImport($this->tenant, $this->organization, staleMinutes: 1);
    recoveryRow($import, 1, ProductImportRowStatus::VALID);

    $this->artisan('products:imports:recover')->assertExitCode(0);

    Queue::assertNothingPushed();
});

test('an import whose rows were all applied is settled instead of re-queued', function () {
    Queue::fake();

    $import = recoveryImport($this->tenant, $this->organization);
    recoveryRow($import, 1, ProductImportRowStatus::IMPORTED);
    recoveryRow($import, 2, ProductImportRowStatus::SKIPPED);

    $this->artisan('products:imports:recover')->assertExitCode(0);

    Queue::assertNothingPushed();

    expect($import->refresh()->status)->toBe(ProductImportStatus::COMPLETED)
        ->and($import->finished_at)->not->toBeNull();
});

test('the dry run reports work without queueing it', function () {
    Queue::fake();

    $import = recoveryImport($this->tenant, $this->organization);
    recoveryRow($import, 1, ProductImportRowStatus::VALID);

    $this->artisan('products:imports:recover', ['--dry-run' => true])->assertExitCode(0);

    Queue::assertNothingPushed();
    expect($import->refresh()->status)->toBe(ProductImportStatus::IMPORTING);
});
