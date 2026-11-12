<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Unit;
use App\Support\Import\ProductImportColumns;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Generates the standard product import template (XLSX + CSV headers).
 *
 * The workbook is the human-facing contract; the CSVs are what the import
 * pipeline reads. Both are generated from the same column list.
 */
class ProductsTemplateCommand extends Command
{
    protected $signature = 'products:template
        {--path= : Directory to write the template files to (defaults to storage/app/templates)}';

    protected $description = 'Generate the product import template (XLSX and CSV headers)';

    public function handle(): int
    {
        $directory = (string) ($this->option('path') ?: storage_path('app/templates'));

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            $this->error("Unable to create {$directory}");

            return self::FAILURE;
        }

        $this->writeWorkbook($directory.'/products-template.xlsx');
        $this->writeCsv($directory.'/products-template.csv', ProductImportColumns::PRODUCTS);
        $this->writeCsv($directory.'/variants-template.csv', ProductImportColumns::VARIANTS);
        $this->writeCsv($directory.'/images-template.csv', ProductImportColumns::IMAGES);

        $this->info("Template written to {$directory}");

        return self::SUCCESS;
    }

    private function writeWorkbook(string $path): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $this->instructionsSheet($spreadsheet);
        $this->dataSheet($spreadsheet, 'Products', ProductImportColumns::PRODUCTS);
        $this->dataSheet($spreadsheet, 'Variants', ProductImportColumns::VARIANTS);
        $this->dataSheet($spreadsheet, 'Images', ProductImportColumns::IMAGES);
        $this->referenceSheet($spreadsheet);

        $spreadsheet->setActiveSheetIndexByName('Products');

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }

    private function instructionsSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Instructions');

        $lines = [
            ['HTAShop product import — standard template'],
            [''],
            ['Format', 'CSV (UTF-8, comma-separated) is the canonical format. XLSX and ODS are accepted and normalised to the same columns.'],
            ['Rows', 'One row per product. Blank rows are ignored. Do not add formulas to any cell.'],
            ['External ID', 'external_id is the re-import key. It is stored as the seller SKU and must be unique per tenant.'],
            ['Action', 'upsert (default) creates or updates; create_only skips an existing external_id; update_only skips a missing one.'],
            ['Categories', 'category_path uses " > " between levels, e.g. Electronics > Sensors & Transducers. Selecting a child path also attaches its parents.'],
            ['Multi-value', 'Separate features, tags, image URLs and channel codes with a pipe: feature one|feature two.'],
            ['Specifications', 'Use key=value pairs separated by a pipe: MCU=ATmega328P|Operating Voltage=5 V.'],
            ['Booleans', 'Use 1/0 (true/false, yes/no are also accepted).'],
            ['Numbers', 'Use a dot for decimals and no thousands separators: 1199.00.'],
            ['GTIN', 'GTIN-8, GTIN-12, GTIN-13 or GTIN-14. The check digit is validated.'],
            ['Prices', 'price is the selling price, sale_price the discounted price, cost_price the purchase price for margin reporting.'],
            ['Stock', 'stock_qty is written to the warehouse given by warehouse_code (or the default warehouse).'],
            ['Images', 'image_urls are downloaded into the media library, resized into four variants and attached to the product. A broken link is a warning, not a failure.'],
            ['Sheets', 'Each sheet is one file: import Products first, then Variants, then Images — pass --kind=products|variants|images (or the same field on the HTTP endpoint).'],
            ['Dry run', 'Always run with --dry-run first; it validates every row and writes nothing.'],
        ];

        $sheet->fromArray($lines, null, 'A1', true);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A3:A15')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(110);
        $sheet->getStyle('B3:B15')->getAlignment()->setWrapText(true);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function dataSheet(Spreadsheet $spreadsheet, string $title, array $columns): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);
        $sheet->fromArray($columns, null, 'A1', true);

        $lastColumn = Coordinate::stringFromColumnIndex(count($columns));
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF2563EB');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");

        for ($index = 1; $index <= count($columns); $index++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setWidth(20);
        }

        if ($title === 'Products') {
            $this->addValidation($sheet, $columns, 'action', ['upsert', 'create_only', 'update_only']);
            $this->addValidation($sheet, $columns, 'status', ['draft', 'active']);
            $this->addValidation($sheet, $columns, 'currency', $this->currencyCodes());
            $this->addValidation($sheet, $columns, 'unit', $this->unitCodes());
            $this->addValidation($sheet, $columns, 'channel_codes', ['storefront', 'marketplace', 'punchout']);
            foreach (['is_active', 'ships_alone', 'is_stackable', 'is_fragile', 'is_hazmat'] as $field) {
                $this->addValidation($sheet, $columns, $field, ['1', '0']);
            }
        }
    }

    private function referenceSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Reference');

        $rows = [
            ['Reference values'],
            [''],
            ['Actions', 'upsert, create_only, update_only'],
            ['Statuses', 'draft, active'],
            ['Currencies', implode(', ', $this->currencyCodes())],
            ['Units', implode(', ', $this->unitCodes())],
            ['Channels', 'storefront, marketplace, punchout'],
            ['Booleans', '1 / 0'],
            [''],
            ['Category paths'],
        ];

        foreach ($this->categoryPaths() as $path) {
            $rows[] = [$path];
        }

        $sheet->fromArray($rows, null, 'A1', true);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A3:A8')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(60);
        $sheet->getColumnDimension('B')->setWidth(60);
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $values
     */
    private function addValidation(Worksheet $sheet, array $columns, string $field, array $values): void
    {
        $index = array_search($field, $columns, true);

        if ($index === false || $values === []) {
            return;
        }

        $column = Coordinate::stringFromColumnIndex($index + 1);
        $validation = $sheet->getCell("{$column}2")->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setFormula1('"'.implode(',', $values).'"');
        $validation->setSqref("{$column}2:{$column}5000");
    }

    /**
     * @return array<int, string>
     */
    private function currencyCodes(): array
    {
        $codes = Currency::query()->pluck('code')->map(fn ($code) => strtoupper((string) $code))->unique()->values()->all();

        return $codes === [] ? ['PKR', 'USD'] : $codes;
    }

    /**
     * @return array<int, string>
     */
    private function unitCodes(): array
    {
        $codes = Unit::query()->orderBy('code')->pluck('code')->map(fn ($code) => (string) $code)->unique()->values()->all();

        return $codes === [] ? ['pc', 'set', 'kit', 'pack'] : $codes;
    }

    /**
     * @return array<int, string>
     */
    private function categoryPaths(): array
    {
        $roots = Category::query()->whereNull('parent_id')->orderBy('sort_order')->get(['id', 'name']);
        $children = Category::query()
            ->whereIn('parent_id', $roots->pluck('id'))
            ->orderBy('sort_order')
            ->get(['parent_id', 'name'])
            ->groupBy('parent_id');

        $paths = [];

        foreach ($roots as $root) {
            $paths[] = $root->name;

            foreach ($children->get($root->id, []) as $child) {
                $paths[] = $root->name.' > '.$child->name;
            }
        }

        return $paths;
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function writeCsv(string $path, array $columns): void
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            return;
        }

        // UTF-8 BOM so Excel opens non-ASCII text correctly; the reader strips it.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $columns, ',', '"', '');
        fclose($handle);
    }
}
