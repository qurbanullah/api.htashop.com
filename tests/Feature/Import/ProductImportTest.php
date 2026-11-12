<?php

namespace Tests\Feature\Import;

use App\Enums\ProductImportRowStatus;
use App\Enums\ProductImportStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Highlight;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\Tenant;
use App\Models\Variant;
use App\Models\Warehouse;
use App\Services\Import\ProductImportService;
use App\Support\Import\ProductImportColumns;
use Database\Seeders\MeasurementSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function (): void {
    Storage::disk('local')->deleteDirectory('imports');

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

    $this->sensors = Category::query()->create([
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
});

afterEach(function (): void {
    Storage::disk('local')->deleteDirectory('imports');
});

/**
 * Write rows as a CSV in the documented column order and return the stored path.
 *
 * @param  array<int, array<string, string>>  $rows
 */
function importCsvPath(array $rows, string $name = 'products.csv', ?array $columns = null): string
{
    $path = "imports/{$name}";
    $columns ??= ProductImportColumns::PRODUCTS;

    $handle = fopen('php://temp', 'r+');
    fputcsv($handle, $columns);

    foreach ($rows as $row) {
        fputcsv($handle, array_map(
            fn (string $column): string => (string) ($row[$column] ?? ''),
            $columns,
        ));
    }

    rewind($handle);
    Storage::disk('local')->put($path, $handle);
    fclose($handle);

    return $path;
}

/**
 * Write the same rows to an XLSX file, mirroring what the template produces.
 *
 * @param  array<int, array<string, string>>  $rows
 */
function importXlsxPath(array $rows, string $name = 'products.xlsx'): string
{
    $path = "imports/{$name}";
    $absolute = Storage::disk('local')->path($path);
    $directory = dirname($absolute);

    if (! is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(ProductImportColumns::PRODUCTS, null, 'A1');

    $line = 2;

    foreach ($rows as $row) {
        $sheet->fromArray(array_map(
            fn (string $column): string => (string) ($row[$column] ?? ''),
            ProductImportColumns::PRODUCTS,
        ), null, 'A'.$line);

        $line++;
    }

    (new Xlsx($spreadsheet))->save($absolute);
    $spreadsheet->disconnectWorksheets();

    return $path;
}

/**
 * @param  array<string, mixed>  $options
 */
function importRecord(string $path, Tenant $tenant, Organization $organization, array $options = [], string $kind = 'products'): ProductImport
{
    return app(ProductImportService::class)->createRecord([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'original_filename' => basename($path),
        'path' => $path,
        'kind' => $kind,
        'size_bytes' => 0,
        'options' => $options,
    ]);
}

/**
 * @param  array<int, array<string, string>>  $rows
 * @param  array<string, mixed>  $options
 */
function importRows(array $rows, Tenant $tenant, Organization $organization, array $options = []): ProductImport
{
    $service = app(ProductImportService::class);
    $import = importRecord(importCsvPath($rows), $tenant, $organization, $options);

    $service->parse($import);

    return $import->refresh();
}

/**
 * Stage rows for a non-product kind (variants or images).
 *
 * @param  array<int, array<string, string>>  $rows
 * @param  array<int, string>  $columns
 * @param  array<string, mixed>  $options
 */
function importStaged(array $rows, array $columns, string $kind, Tenant $tenant, Organization $organization, array $options = []): ProductImport
{
    $service = app(ProductImportService::class);
    $import = importRecord(
        importCsvPath($rows, "{$kind}.csv", $columns),
        $tenant,
        $organization,
        $options,
        $kind,
    );

    $service->parse($import);

    return $import->refresh();
}

/**
 * A real JPEG, so the ingestor's dimension and resize path is exercised.
 */
function importJpegBytes(int $width = 1200, int $height = 800): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 30, 80, 200));

    ob_start();
    imagejpeg($image, null, 80);
    $bytes = (string) ob_get_clean();

    imagedestroy($image);

    return $bytes;
}

/**
 * A complete, realistic row that passes every validation rule.
 *
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function importRow(array $overrides = []): array
{
    return array_merge([
        'external_id' => 'HTA-ARDUINO-UNO',
        'name' => 'Arduino UNO R3',
        'slug' => 'arduino-uno-r3',
        'category_path' => 'Electronics > Sensors & Transducers',
        'brand' => 'Arduino',
        'summary' => 'Classic ATmega328P development board.',
        'description_html' => '<p>The <strong>Arduino UNO R3</strong> is the reference board.</p>',
        'features' => 'ATmega328P|14 digital I/O|6 analog inputs',
        'specs' => 'Microcontroller=ATmega328P|Clock Speed=16 MHz',
        'tags' => 'arduino|development-board',
        'status' => 'active',
        'is_active' => '1',
        'part_number' => 'A000066',
        'gtin' => '4006381333931',
        'currency' => 'PKR',
        'price' => '2450',
        'sale_price' => '1999',
        'cost_price' => '1500',
        'unit' => '',
        'stock_qty' => '50',
        'warehouse_code' => 'MAIN',
        'low_stock_threshold' => '5',
        'weight_kg' => '0.09',
        'length_cm' => '8',
        'width_cm' => '5',
        'height_cm' => '2',
        'ships_alone' => '0',
        'is_stackable' => '1',
        'is_fragile' => '0',
        'is_hazmat' => '0',
        'whats_included' => 'Arduino UNO board|USB cable',
        'packaging_notes' => 'Anti-static bag.',
        'channel_codes' => 'storefront',
        'seo_title' => 'Arduino UNO R3 in Pakistan',
        'seo_description' => 'Buy the original Arduino UNO R3 online.',
    ], $overrides);
}

test('a csv file is parsed into staged rows and applied to the catalogue', function () {
    $import = importRows([importRow()], $this->tenant, $this->organization);

    expect($import->status)->toBe(ProductImportStatus::VALIDATING)
        ->and($import->format)->toBe('csv')
        ->and($import->total_rows)->toBe(1)
        ->and($import->failed_count)->toBe(0);

    $row = $import->rows()->first();

    expect($row->status)->toBe(ProductImportRowStatus::VALID)
        ->and($row->external_id)->toBe('HTA-ARDUINO-UNO')
        ->and($row->errors)->toBeNull();

    app(ProductImportService::class)->apply($import->refresh());
    $import->refresh();

    expect($import->status)->toBe(ProductImportStatus::COMPLETED)
        ->and($import->created_count)->toBe(1)
        ->and($import->failed_count)->toBe(0)
        ->and($import->finished_at)->not->toBeNull();

    $product = Product::query()->where('seller_sku', 'HTA-ARDUINO-UNO')->firstOrFail();

    expect($product->tenant_id)->toBe($this->tenant->id)
        ->and($product->organization_id)->toBe($this->organization->id)
        ->and($product->name)->toBe('Arduino UNO R3')
        ->and($product->status)->toBe('active')
        ->and($product->is_active)->toBeTrue()
        ->and($product->part_number)->toBe('A000066')
        ->and($product->gtin)->toBe('4006381333931')
        ->and((float) $product->cost_price)->toBe(1500.0)
        ->and($product->description)->toContain('reference board');

    // Both the leaf and its ancestor are attached, so the product is reachable
    // from the top-level catalogue too.
    expect($product->categories()->pluck('categories.id')->all())
        ->toEqualCanonicalizing([$this->electronics->id, $this->sensors->id]);

    expect($product->features()->pluck('name')->all())
        ->toEqualCanonicalizing(['ATmega328P', '14 digital I/O', '6 analog inputs']);

    expect($product->tags()->pluck('name')->all())
        ->toEqualCanonicalizing(['arduino', 'development-board']);

    expect($product->brands()->pluck('name')->all())->toBe(['Arduino']);

    expect((float) $product->metadata['price'])->toBe(2450.0)
        ->and((float) $product->metadata['sale_price'])->toBe(1999.0)
        ->and($product->metadata['currency'])->toBe('PKR')
        ->and($product->metadata['specs'])->toHaveCount(2)
        ->and($product->metadata['specs'][0])
        ->toMatchArray(['key' => 'Microcontroller', 'value' => 'ATmega328P']);

    $inventory = $product->inventories()->firstOrFail();

    expect($inventory->warehouse_id)->toBe($this->warehouse->id)
        ->and((float) $inventory->quantity)->toBe(50.0)
        ->and((float) $inventory->low_stock_threshold)->toBe(5.0);

    $shipping = $product->shipping()->firstOrFail();

    expect((float) $shipping->gross_weight)->toBe(0.09)
        ->and((float) $shipping->length)->toBe(8.0)
        ->and($shipping->ships_alone)->toBeFalse()
        ->and($shipping->is_stackable)->toBeTrue()
        ->and($shipping->contents)->toBe(['Arduino UNO board', 'USB cable'])
        ->and($shipping->handling_notes)->toBe('Anti-static bag.');

    expect($import->rows()->first()->product_id)->toBe($product->id);
});

test('a dry run validates every row without writing products', function () {
    $service = app(ProductImportService::class);
    $import = importRecord(importCsvPath([importRow()]), $this->tenant, $this->organization, [
        'dry_run' => true,
    ]);

    $service->parse($import);
    $service->completeDryRun($import->refresh());
    $import->refresh();

    expect($import->status)->toBe(ProductImportStatus::COMPLETED)
        ->and($import->total_rows)->toBe(1)
        ->and($import->created_count)->toBe(0)
        ->and(Product::query()->count())->toBe(0);
});

test('re-importing the same external id updates the product instead of duplicating it', function () {
    $service = app(ProductImportService::class);

    $first = importRecord(importCsvPath([importRow(['brand' => ''])]), $this->tenant, $this->organization);
    $service->parse($first);
    $service->apply($first->refresh());

    $product = Product::query()->where('seller_sku', 'HTA-ARDUINO-UNO')->firstOrFail();
    $sku = $product->sku;
    $slug = $product->slug;

    $second = importRecord(importCsvPath([
        importRow([
            'brand' => '',
            'name' => 'Arduino UNO R3 (Clone)',
            'price' => '2200',
            'stock_qty' => '80',
        ]),
    ]), $this->tenant, $this->organization);

    $service->parse($second);
    $service->apply($second->refresh());
    $second->refresh();

    expect($second->status)->toBe(ProductImportStatus::COMPLETED)
        ->and($second->created_count)->toBe(0)
        ->and($second->updated_count)->toBe(1)
        ->and(Product::query()->count())->toBe(1);

    $product->refresh();

    expect($product->name)->toBe('Arduino UNO R3 (Clone)')
        ->and($product->slug)->toBe($slug)
        ->and($product->sku)->toBe($sku)
        ->and($product->seller_sku)->toBe('HTA-ARDUINO-UNO')
        ->and((float) $product->metadata['price'])->toBe(2200.0)
        ->and((float) $product->inventories()->firstOrFail()->quantity)->toBe(80.0);
});

test('invalid rows are reported without blocking the valid ones', function () {
    $import = importRows([
        importRow(['external_id' => '', 'name' => 'No external id']),
        importRow(['external_id' => 'HTA-BAD-CATEGORY', 'category_path' => 'Nope > Missing']),
        importRow(['external_id' => 'HTA-BAD-GTIN', 'gtin' => '1234567890123']),
        importRow(['external_id' => 'HTA-BAD-PRICE', 'price' => 'not-a-number']),
        importRow(['external_id' => 'HTA-GOOD-ROW', 'name' => 'Good Row']),
    ], $this->tenant, $this->organization);

    expect($import->total_rows)->toBe(5)
        ->and($import->failed_count)->toBe(4);

    $rows = $import->rows()->orderBy('row_number')->get();
    $statuses = $rows->pluck('status')->all();

    expect($statuses)->toBe([
        ProductImportRowStatus::INVALID,
        ProductImportRowStatus::INVALID,
        ProductImportRowStatus::INVALID,
        ProductImportRowStatus::INVALID,
        ProductImportRowStatus::VALID,
    ]);

    expect($rows[0]->errors)->toHaveKey('external_id')
        ->and($rows[1]->errors)->toHaveKey('category_path')
        ->and($rows[2]->errors)->toHaveKey('gtin')
        ->and($rows[3]->errors)->toHaveKey('price');

    app(ProductImportService::class)->apply($import->refresh());
    $import->refresh();

    expect($import->status)->toBe(ProductImportStatus::COMPLETED_WITH_ERRORS)
        ->and($import->created_count)->toBe(1)
        ->and($import->failed_count)->toBe(4)
        ->and(Product::query()->pluck('seller_sku')->all())->toBe(['HTA-GOOD-ROW']);
});

test('stock quantity without a warehouse is rejected', function () {
    $import = importRows([
        importRow(['external_id' => 'HTA-NO-WAREHOUSE', 'warehouse_code' => 'MISSING']),
    ], $this->tenant, $this->organization);

    expect($import->rows()->first()->status)->toBe(ProductImportRowStatus::INVALID)
        ->and($import->rows()->first()->errors)->toHaveKey('warehouse_code');
});

test('create missing opt in creates categories and brands', function () {
    $rows = [
        importRow([
            'external_id' => 'HTA-CUSTOM-WIDGET',
            'name' => 'Custom Widget',
            'category_path' => 'Electronics > Custom Widgets',
            'brand' => 'Widget Works',
        ]),
    ];

    $without = importRows($rows, $this->tenant, $this->organization);

    expect($without->rows()->first()->status)->toBe(ProductImportRowStatus::INVALID)
        ->and($without->rows()->first()->errors)->toHaveKey('category_path')
        ->and(Category::query()->where('slug', 'custom-widgets')->exists())->toBeFalse();

    $with = importRows($rows, $this->tenant, $this->organization, ['create_missing' => true]);

    expect($with->rows()->first()->status)->toBe(ProductImportRowStatus::VALID);

    app(ProductImportService::class)->apply($with->refresh());

    $category = Category::query()->where('slug', 'custom-widgets')->firstOrFail();
    $brand = Brand::query()->where('slug', 'widget-works')->firstOrFail();

    expect($category->parent_id)->toBe($this->electronics->id)
        ->and($brand->tenant_id)->toBe($this->tenant->id)
        ->and($brand->organization_id)->toBe($this->organization->id);

    $product = Product::query()->where('seller_sku', 'HTA-CUSTOM-WIDGET')->firstOrFail();

    expect($product->categories()->pluck('categories.id')->all())
        ->toEqualCanonicalizing([$this->electronics->id, $category->id])
        ->and($product->brands()->pluck('name')->all())->toBe(['Widget Works']);
});

test('create only and update only restrict what a row may touch', function () {
    $service = app(ProductImportService::class);

    $seed = importRecord(importCsvPath([
        importRow(['external_id' => 'HTA-EXISTING', 'brand' => '']),
    ]), $this->tenant, $this->organization);
    $service->parse($seed);
    $service->apply($seed->refresh());

    $import = importRecord(importCsvPath([
        importRow(['external_id' => 'HTA-EXISTING', 'brand' => '', 'action' => 'create_only', 'price' => '9999']),
        importRow(['external_id' => 'HTA-NEW-ROW', 'brand' => '', 'action' => 'create_only']),
        importRow(['external_id' => 'HTA-NOT-THERE', 'brand' => '', 'action' => 'update_only']),
    ]), $this->tenant, $this->organization);

    $service->parse($import);
    $service->apply($import->refresh());
    $import->refresh();

    expect($import->created_count)->toBe(1)
        ->and($import->skipped_count)->toBe(2)
        ->and($import->failed_count)->toBe(0)
        ->and(Product::query()->count())->toBe(2)
        ->and((float) Product::query()->where('seller_sku', 'HTA-EXISTING')->firstOrFail()->metadata['price'])
        ->toBe(2450.0);

    $skipped = $import->rows()
        ->where('status', ProductImportRowStatus::SKIPPED)
        ->pluck('external_id')
        ->all();

    expect($skipped)->toBe(['HTA-EXISTING', 'HTA-NOT-THERE']);
});

test('an xlsx file produces the same staged payload as the equivalent csv', function () {
    $rows = [importRow(), importRow(['external_id' => 'HTA-ARDUINO-NANO', 'name' => 'Arduino Nano'])];

    $csvImport = importRows($rows, $this->tenant, $this->organization);

    $xlsxPath = importXlsxPath($rows);
    $xlsxImport = importRecord($xlsxPath, $this->tenant, $this->organization);
    app(ProductImportService::class)->parse($xlsxImport);
    $xlsxImport->refresh();

    expect($xlsxImport->format)->toBe('xlsx')
        ->and($xlsxImport->total_rows)->toBe(2)
        ->and($xlsxImport->failed_count)->toBe(0);

    $csvPayloads = $csvImport->rows()->orderBy('row_number')->get()->pluck('normalized')->all();
    $xlsxPayloads = $xlsxImport->rows()->orderBy('row_number')->get()->pluck('normalized')->all();

    expect($xlsxPayloads)->toEqual($csvPayloads);
});

test('blank action, status and currency fall back to their documented defaults', function () {
    $row = importRow([
        'external_id' => 'HTA-DEFAULTS',
        'action' => '',
        'status' => '',
        'currency' => '',
        'is_active' => '',
        'stock_qty' => '',
    ]);

    $import = importRows([$row], $this->tenant, $this->organization);

    expect($import->rows()->first()->status)->toBe(ProductImportRowStatus::VALID);

    app(ProductImportService::class)->apply($import->refresh());

    $product = Product::query()->where('seller_sku', 'HTA-DEFAULTS')->firstOrFail();

    expect($product->status)->toBe('draft')
        ->and($product->is_active)->toBeFalse()
        ->and($product->metadata['currency'])->toBe('PKR');
});

test('the apply step is resumable in bounded chunks', function () {
    $rows = [];

    foreach (range(1, 3) as $index) {
        $rows[] = importRow([
            'external_id' => "HTA-CHUNK-{$index}",
            'name' => "Chunk {$index}",
        ]);
    }

    $import = importRows($rows, $this->tenant, $this->organization);
    $service = app(ProductImportService::class);

    $service->beginApply($import);

    $first = $service->applyChunk($import, 0, 2);

    expect($first['processed'])->toBe(2)
        ->and($first['has_more'])->toBeTrue()
        ->and(Product::query()->count())->toBe(2)
        ->and($import->refresh()->processed_rows)->toBe(2);

    $second = $service->applyChunk($import, $first['next_row_id'], 2);

    expect($second['processed'])->toBe(1)
        ->and($second['has_more'])->toBeFalse()
        ->and(Product::query()->count())->toBe(3);

    $service->finishApply($import->refresh());

    expect($import->refresh()->status)->toBe(ProductImportStatus::COMPLETED);
});

test('remote product images are stored in the dam with resized variants', function () {
    Storage::fake('idrivee2');
    Http::fake([
        'https://cdn.example.com/*' => Http::response(importJpegBytes(1200, 800), 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    $import = importRows([
        importRow([
            'image_urls' => 'https://cdn.example.com/uno.jpg']),
    ], $this->tenant, $this->organization);

    app(ProductImportService::class)->apply($import->refresh());

    $product = Product::query()->where('seller_sku', 'HTA-ARDUINO-UNO')->firstOrFail();
    $dam = $product->dams()->firstOrFail();

    expect($dam->collection_name)->toBe('featured')
        ->and($dam->origin_url)->toBe('https://cdn.example.com/uno.jpg')
        ->and($dam->metadata['variants'])
        ->toHaveKeys(['original', 'large', 'medium', 'small', 'thumb'])
        ->and((int) $dam->metadata['width'])->toBe(1200);

    Storage::disk('idrivee2')->assertExists($dam->object_key);

    // The variants are real files, not aliases of the original.
    expect($dam->metadata['variants']['medium'])->not->toBe($dam->metadata['variants']['original'])
        ->and($dam->metadata['variants']['thumb'])->not->toBe($dam->metadata['variants']['original']);

    Storage::disk('idrivee2')->assertExists($dam->metadata['variants']['medium']);
    Storage::disk('idrivee2')->assertExists($dam->metadata['variants']['thumb']);
});

test('re-importing the same image does not duplicate it', function () {
    Storage::fake('idrivee2');
    Http::fake([
        'https://cdn.example.com/*' => Http::response(importJpegBytes(), 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    $service = app(ProductImportService::class);

    $first = importRows([
        importRow(['image_urls' => 'https://cdn.example.com/uno.jpg']),
    ], $this->tenant, $this->organization);
    $service->apply($first->refresh());

    $second = importRows([
        importRow(['image_urls' => 'https://cdn.example.com/uno.jpg']),
    ], $this->tenant, $this->organization);
    $service->apply($second->refresh());

    $product = Product::query()->where('seller_sku', 'HTA-ARDUINO-UNO')->firstOrFail();

    expect($product->dams()->count())->toBe(1);
});

test('a broken image URL is a warning and never blocks the product row', function () {
    Storage::fake('idrivee2');
    Http::fake(['*' => Http::response('gone', 404)]);

    $import = importRows([
        importRow(['image_urls' => 'https://cdn.example.com/missing.jpg']),
    ], $this->tenant, $this->organization);

    app(ProductImportService::class)->apply($import->refresh());
    $import->refresh();

    $product = Product::query()->where('seller_sku', 'HTA-ARDUINO-UNO')->firstOrFail();
    $row = $import->rows()->firstOrFail();

    expect($product->dams()->count())->toBe(0)
        ->and($import->created_count)->toBe(1)
        ->and($import->failed_count)->toBe(0)
        ->and($row->warnings)->toHaveCount(1)
        ->and($row->warnings[0])->toContain('miss');
});

test('images can be imported as their own file for existing products', function () {
    Storage::fake('idrivee2');
    Http::fake([
        'https://cdn.example.com/uno.jpg' => Http::response(importJpegBytes(1200, 800), 200, [
            'Content-Type' => 'image/jpeg',
        ]),
        'https://cdn.example.com/uno-side.jpg' => Http::response(importJpegBytes(900, 900), 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    $service = app(ProductImportService::class);

    $products = importRows([
        importRow(['image_urls' => '']),
        importRow(['external_id' => 'HTA-ARDUINO-NANO', 'name' => 'Arduino Nano', 'image_urls' => '']),
    ], $this->tenant, $this->organization);
    $service->apply($products->refresh());

    $images = importStaged([
        [
            'external_id' => 'HTA-ARDUINO-UNO',
            'image_url' => 'https://cdn.example.com/uno.jpg',
            'alt' => 'Arduino UNO front',
            'sort_order' => '1',
            'is_featured' => '1',
        ],
        [
            'external_id' => 'HTA-ARDUINO-UNO',
            'image_url' => 'https://cdn.example.com/uno-side.jpg',
            'sort_order' => '2',
            'is_featured' => '0',
        ],
        [
            'external_id' => 'HTA-UNKNOWN',
            'image_url' => 'https://cdn.example.com/x.jpg',
        ],
    ], ProductImportColumns::IMAGES, 'images', $this->tenant, $this->organization);

    expect($images->failed_count)->toBe(1);

    $service->apply($images->refresh());
    $images->refresh();

    $product = Product::query()->where('seller_sku', 'HTA-ARDUINO-UNO')->firstOrFail();
    $nano = Product::query()->where('seller_sku', 'HTA-ARDUINO-NANO')->firstOrFail();

    expect($product->dams()->count())->toBe(2)
        ->and($product->dams()->where('collection_name', 'featured')->count())->toBe(1)
        ->and($nano->dams()->count())->toBe(0)
        ->and($images->created_count + $images->updated_count)->toBe(2)
        ->and($images->failed_count)->toBe(1)
        ->and($images->rows()->where('status', 'invalid')->value('external_id'))->toBe('HTA-UNKNOWN');
});

test('variants can be imported for an existing product and updated by their own SKU', function () {
    $service = app(ProductImportService::class);

    $products = importRows(
        [importRow(['image_urls' => ''])],
        $this->tenant,
        $this->organization,
        ['download_images' => false],
    );
    $service->apply($products->refresh());

    $rows = [
        [
            'parent_external_id' => 'HTA-ARDUINO-UNO',
            'external_id' => 'HTA-UNO-BLUE',
            'name' => 'Arduino UNO R3 - Blue',
            'price' => '1299',
            'stock_qty' => '12',
            'warehouse_code' => 'MAIN',
            'option_1_name' => 'Colour',
            'option_1_value' => 'Blue',
        ],
        [
            'parent_external_id' => 'HTA-ARDUINO-UNO',
            'external_id' => 'HTA-UNO-RED',
            'price' => '1299',
            'option_1_name' => 'Colour',
            'option_1_value' => 'Red',
        ],
        [
            'parent_external_id' => 'HTA-MISSING',
            'external_id' => 'HTA-ORPHAN',
            'option_1_name' => 'Colour',
            'option_1_value' => 'Green',
        ],
    ];

    $variants = importStaged($rows, ProductImportColumns::VARIANTS, 'variants', $this->tenant, $this->organization);

    expect($variants->failed_count)->toBe(1);

    $service->apply($variants->refresh());
    $variants->refresh();

    $product = Product::query()->where('seller_sku', 'HTA-ARDUINO-UNO')->firstOrFail();

    expect($product->variants()->count())->toBe(2)
        ->and($variants->created_count)->toBe(2);

    $blue = Variant::query()->where('seller_sku', 'HTA-UNO-BLUE')->firstOrFail();

    expect($blue->product_id)->toBe($product->id)
        ->and($blue->configuration)->toBe(['colour' => 'Blue'])
        ->and($blue->sku)->not->toBeEmpty()
        ->and((float) $blue->metadata['price'])->toBe(1299.0)
        ->and($blue->is_default)->toBeTrue();

    $inventory = $blue->inventories()->firstOrFail();

    expect((float) $inventory->quantity)->toBe(12.0)
        ->and($inventory->warehouse_id)->toBe($this->warehouse->id);

    $sku = $blue->sku;

    $again = importStaged([
        [
            'parent_external_id' => 'HTA-ARDUINO-UNO',
            'external_id' => 'HTA-UNO-BLUE',
            'price' => '1399',
            'option_1_name' => 'Colour',
            'option_1_value' => 'Blue',
        ],
    ], ProductImportColumns::VARIANTS, 'variants', $this->tenant, $this->organization);

    $service->apply($again->refresh());
    $again->refresh();

    $blue->refresh();

    expect($again->updated_count)->toBe(1)
        ->and($product->variants()->count())->toBe(2)
        ->and($blue->sku)->toBe($sku)
        ->and((float) $blue->metadata['price'])->toBe(1399.0);
});

test('product highlights are imported and reused across re-imports', function () {
    $service = app(ProductImportService::class);

    $rows = [
        importRow([
            'image_urls' => '',
            'highlights' => 'Beginner friendly=Works with the official Arduino IDE and thousands of tutorials.|Shield compatible=Stacks with the standard UNO shield footprint.|5 V logic',
        ]),
    ];

    $import = importRows($rows, $this->tenant, $this->organization, ['download_images' => false]);
    $service->apply($import->refresh());

    $product = Product::query()->where('seller_sku', 'HTA-ARDUINO-UNO')->firstOrFail();

    expect($product->highlights()->count())->toBe(3)
        ->and(Highlight::query()->count())->toBe(3);

    $first = $product->highlights()->first();

    expect($first->heading)->toBe('Beginner friendly')
        ->and($first->body)->toBe('Works with the official Arduino IDE and thousands of tutorials.')
        ->and($first->pivot->sort_order)->toBe(0);

    // A bare entry keeps no heading and is rendered as a plain bullet.
    expect($product->highlights()->get()->last()->heading)->toBeNull();

    // Re-importing the same file reuses the records instead of duplicating them.
    $again = importRows($rows, $this->tenant, $this->organization, ['download_images' => false]);
    $service->apply($again->refresh());

    expect($product->highlights()->count())->toBe(3)
        ->and(Highlight::query()->count())->toBe(3);

    // A shorter list becomes the new truth for the product.
    $trimmed = importRows([
        importRow(['image_urls' => '', 'highlights' => 'Beginner friendly=Works with the official Arduino IDE and thousands of tutorials.']),
    ], $this->tenant, $this->organization, ['download_images' => false]);
    $service->apply($trimmed->refresh());

    expect($product->highlights()->count())->toBe(1);
});

test('a blank highlights cell leaves existing highlights untouched', function () {
    $service = app(ProductImportService::class);

    $with = importRows([
        importRow(['image_urls' => '', 'highlights' => 'Built for prototyping=Standard 0.1 inch headers.']),
    ], $this->tenant, $this->organization, ['download_images' => false]);
    $service->apply($with->refresh());

    $product = Product::query()->where('seller_sku', 'HTA-ARDUINO-UNO')->firstOrFail();

    expect($product->highlights()->count())->toBe(1);

    $without = importRows([
        importRow(['image_urls' => '', 'highlights' => '']),
    ], $this->tenant, $this->organization, ['download_images' => false]);
    $service->apply($without->refresh());

    expect($product->highlights()->count())->toBe(1);
});

test('the import command validates and applies a file', function () {
    $path = Storage::disk('local')->path(importCsvPath([importRow(['brand' => ''])]));

    $this->artisan('products:import', [
        'file' => $path,
        '--dry-run' => true,
    ])->assertExitCode(0);

    expect(Product::query()->count())->toBe(0);

    $this->artisan('products:import', [
        'file' => $path,
        '--no-reindex' => true,
    ])->assertExitCode(0);

    expect(Product::query()->where('seller_sku', 'HTA-ARDUINO-UNO')->exists())->toBeTrue();
});
