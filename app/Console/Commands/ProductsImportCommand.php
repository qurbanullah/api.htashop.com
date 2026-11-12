<?php

namespace App\Console\Commands;

use App\Enums\ProductImportKind;
use App\Enums\ProductImportRowStatus;
use App\Enums\ProductImportStatus;
use App\Models\Organization;
use App\Models\ProductImport;
use App\Models\ProductImportRow;
use App\Models\Tenant;
use App\Services\Import\ProductImportService;
use App\Services\Search\ProductSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Import products from a CSV, XLSX or ODS file.
 *
 * Parsing and applying are separate phases: `--dry-run` validates and stages
 * every row without writing to the catalogue, so a bad file is caught first.
 */
class ProductsImportCommand extends Command
{
    protected $signature = 'products:import
        {file : Absolute path to a .csv, .xlsx or .ods file}
        {--kind=products : products, variants or images}
        {--tenant= : Tenant id or slug}
        {--organization= : Organization id or slug}
        {--warehouse= : Default warehouse code or slug}
        {--sheet= : Sheet to read (spreadsheet files)}
        {--create-missing : Create missing categories, brands and manufacturers}
        {--no-images : Skip downloading remote product images}
        {--dry-run : Validate only; do not write products}
        {--no-reindex : Skip the Typesense reindex after a successful import}';

    protected $description = 'Validate and import products from a CSV, XLSX or ODS file';

    public function handle(ProductImportService $service, ProductSearchService $search): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $kind = (string) $this->option('kind');

        if (! in_array($kind, ProductImportKind::ALL, true)) {
            $this->error('Use --kind=products, --kind=variants or --kind=images.');

            return self::FAILURE;
        }

        $tenant = $this->resolveTenant();

        if ($tenant === null) {
            return self::FAILURE;
        }

        $organization = $this->resolveOrganization($tenant);

        if ($organization === null) {
            return self::FAILURE;
        }

        $uuid = (string) Str::uuid();
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $storedPath = "imports/{$uuid}.{$extension}";
        $stream = fopen($file, 'rb');

        Storage::disk('local')->put($storedPath, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        $import = $service->createRecord([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'original_filename' => basename($file),
            'path' => $storedPath,
            'kind' => $kind,
            'size_bytes' => (int) filesize($file),
            'options' => array_filter([
                'create_missing' => (bool) $this->option('create-missing'),
                'warehouse' => $this->option('warehouse'),
                'sheet' => $this->option('sheet'),
                'dry_run' => (bool) $this->option('dry-run'),
                'download_images' => ! (bool) $this->option('no-images'),
            ], fn ($value): bool => $value !== null),
        ]);

        $this->info("Import {$import->uuid} started for {$tenant->name} / {$organization->name}.");

        try {
            $service->parse($import);
        } catch (\Throwable $exception) {
            $this->error('Parsing failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $import->refresh();
        $this->reportValidation($import);

        if ((bool) $this->option('dry-run')) {
            $service->completeDryRun($import);
            $this->warn('Dry run: no products were written.');

            return self::SUCCESS;
        }

        $service->apply($import);
        $import->refresh();

        $this->info(sprintf(
            'Applied %d rows: %d created, %d updated, %d skipped, %d failed.',
            (int) $import->processed_rows,
            (int) $import->created_count,
            (int) $import->updated_count,
            (int) $import->skipped_count,
            (int) $import->failed_count,
        ));

        $this->reportFailures($import);

        $withWarnings = ProductImportRow::query()
            ->where('import_id', $import->id)
            ->whereNotNull('warnings')
            ->count();

        if ($withWarnings > 0) {
            $this->warn("{$withWarnings} rows imported with warnings (for example a skipped image). See the row report.");
        }

        if (! (bool) $this->option('no-reindex') && $kind === ProductImportKind::PRODUCTS && $search->isEnabled()) {
            $search->ensureCollection();
            $this->info("Search index updated ({$search->reindexAll()} products).");
        }

        if ($import->status === ProductImportStatus::FAILED) {
            $this->error('Import failed: '.($import->error_message ?? 'unknown error'));

            return self::FAILURE;
        }

        if ((int) $import->failed_count > 0) {
            $this->warn('Some rows could not be imported. Fix them and re-run the file; existing products are updated by external_id.');
        }

        return self::SUCCESS;
    }

    private function resolveTenant(): ?Tenant
    {
        $value = $this->option('tenant');

        if ($value !== null && $value !== '') {
            $tenant = Tenant::query()
                ->where(fn ($query) => $query->where('id', $value)->orWhere('slug', $value))
                ->first();

            if ($tenant === null) {
                $this->error("Tenant '{$value}' was not found.");
            }

            return $tenant;
        }

        $tenants = Tenant::query()->limit(2)->get();

        if ($tenants->count() !== 1) {
            $this->error('Pass --tenant= when more than one tenant exists.');

            return null;
        }

        return $tenants->first();
    }

    private function resolveOrganization(Tenant $tenant): ?Organization
    {
        $value = $this->option('organization');

        if ($value !== null && $value !== '') {
            $organization = Organization::query()
                ->where('tenant_id', $tenant->id)
                ->where(fn ($query) => $query->where('id', $value)->orWhere('slug', $value))
                ->first();

            if ($organization === null) {
                $this->error("Organization '{$value}' was not found in tenant {$tenant->name}.");
            }

            return $organization;
        }

        $organizations = Organization::query()->where('tenant_id', $tenant->id)->limit(2)->get();

        if ($organizations->count() !== 1) {
            $this->error('Pass --organization= when the tenant has more than one organization.');

            return null;
        }

        return $organizations->first();
    }

    private function reportValidation(ProductImport $import): void
    {
        $valid = ProductImportRow::query()
            ->where('import_id', $import->id)
            ->where('status', ProductImportRowStatus::VALID)
            ->count();
        $invalid = ProductImportRow::query()
            ->where('import_id', $import->id)
            ->where('status', ProductImportRowStatus::INVALID)
            ->count();

        $this->info("Parsed {$import->total_rows} rows: {$valid} valid, {$invalid} invalid.");

        if ($invalid === 0) {
            return;
        }

        $rows = ProductImportRow::query()
            ->where('import_id', $import->id)
            ->where('status', ProductImportRowStatus::INVALID)
            ->orderBy('row_number')
            ->limit(20)
            ->get(['row_number', 'errors']);

        foreach ($rows as $row) {
            $error = collect((array) $row->errors)->map(
                fn ($messages, $field) => $field.': '.(is_array($messages) ? implode(' ', $messages) : $messages),
            )->implode(' | ');

            $this->line("  row {$row->row_number}: {$error}");
        }

        if ($invalid > 20) {
            $this->line('  … '.(($invalid - 20)).' more invalid rows.');
        }
    }

    private function reportFailures(ProductImport $import): void
    {
        $rows = ProductImportRow::query()
            ->where('import_id', $import->id)
            ->where('status', ProductImportRowStatus::FAILED)
            ->orderBy('row_number')
            ->limit(20)
            ->get(['row_number', 'external_id', 'errors']);

        foreach ($rows as $row) {
            $this->line("  row {$row->row_number} ({$row->external_id}): ".json_encode($row->errors));
        }
    }
}
