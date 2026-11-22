<?php

namespace App\Services\Punchout;

use App\Actions\Punchout\EnsureBuyerPartyAction;
use App\Models\BuyerItem;
use App\Models\BuyerItemMapping;
use App\Models\Product;
use App\Models\PunchoutBuyer;
use App\Models\Variant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Imports a buyer's item master.
 *
 * The dry run is the default: it classifies every row (create, reuse, conflict,
 * suggestion, unmatched) without writing. Applying performs the same work in
 * batches, reusing the shared buyer item and only adding this tenant's mappings.
 *
 * Rows without `our_sku` are never auto-mapped unless `apply_suggestions` is
 * sent; matching by GTIN then manufacturer part number is a suggestion, not a
 * decision.
 */
class BuyerPartNumberImportService
{
    private const REQUIRED_COLUMN = 'buyer_part_number';

    private const MAX_REPORT_ROWS = 1000;

    private const CHUNK_SIZE = 500;

    public function __construct(
        protected BuyerItemMappingService $buyerItemMappingService,
        protected EnsureBuyerPartyAction $ensureBuyerPartyAction,
    ) {}

    /**
     * @param  array{dry_run?: bool, remap?: bool, apply_suggestions?: bool}  $options
     * @return array<string, mixed>
     */
    public function handle(PunchoutBuyer $buyer, UploadedFile $file, array $options = []): array
    {
        $dryRun = (bool) data_get($options, 'dry_run', true);
        $remap = (bool) data_get($options, 'remap', false);
        $applySuggestions = (bool) data_get($options, 'apply_suggestions', false);

        $this->ensureBuyerPartyAction->handle($buyer);

        $stream = fopen($file->getRealPath(), 'r');

        if ($stream === false) {
            throw ValidationException::withMessages(['file' => ['The uploaded file could not be read.']]);
        }

        try {
            $columns = $this->readHeader($stream);
            $report = $this->newReport($dryRun);
            $seen = [];
            $pending = [];
            $line = 1;

            while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $line++;

                if ($values === [null] || $values === []) {
                    continue;
                }

                $row = $this->classify($buyer, $columns, $values, $line, $remap, $applySuggestions, $seen);

                $report['totals']['rows']++;
                $report['totals'][$row['action']]++;

                if (count($report['rows']) < self::MAX_REPORT_ROWS) {
                    $report['rows'][] = $row;
                }

                if (! $dryRun && in_array($row['action'], ['create', 'remap'], true) && $row['_payload'] !== null) {
                    $pending[] = $row;

                    if (count($pending) >= self::CHUNK_SIZE) {
                        $this->applyChunk($buyer, $pending, $report);
                        $pending = [];
                    }
                }
            }

            if (! $dryRun && $pending !== []) {
                $this->applyChunk($buyer, $pending, $report);
            }
        } finally {
            fclose($stream);
        }

        $report['truncated'] = $report['totals']['rows'] > count($report['rows']);

        // Internal classification payload never leaves the service.
        $report['rows'] = array_map(function (array $row): array {
            unset($row['_payload']);

            return $row;
        }, $report['rows']);

        return $report;
    }

    /**
     * @param  resource  $stream
     * @return array<string, int>
     */
    private function readHeader($stream): array
    {
        $header = fgetcsv($stream, 0, ',', '"', '');

        if ($header === false) {
            throw ValidationException::withMessages(['file' => ['The file is empty.']]);
        }

        $columns = [];

        foreach ($header as $position => $name) {
            $name = trim(str_replace("\u{FEFF}", '', (string) $name));

            if ($name !== '') {
                $columns[strtolower($name)] = $position;
            }
        }

        if (! array_key_exists(self::REQUIRED_COLUMN, $columns)) {
            throw ValidationException::withMessages([
                'file' => ['The CSV must contain a "buyer_part_number" column.'],
            ]);
        }

        return $columns;
    }

    /**
     * @param  array<string, int>  $columns
     * @param  array<int, string|null>  $values
     * @param  array<string, bool>  $seen
     * @return array<string, mixed>
     */
    private function classify(
        PunchoutBuyer $buyer,
        array $columns,
        array $values,
        int $line,
        bool $remap,
        bool $applySuggestions,
        array &$seen,
    ): array {
        $value = fn (string $column): string => trim((string) $this->cell($columns, $values, $column));

        $code = $value('buyer_part_number');
        $row = [
            'line' => $line,
            'buyer_part_number' => $code,
            'action' => 'invalid',
            'product_sku' => null,
            'variant_sku' => null,
            'suggested_by' => null,
            'message' => null,
            '_payload' => null,
        ];

        if ($code === '') {
            $row['message'] = 'Missing buyer part number.';

            return $row;
        }

        $key = BuyerItem::keyFor($code);

        if (isset($seen[$key])) {
            $row['action'] = 'duplicate';
            $row['message'] = 'The same code appears earlier in this file.';

            return $row;
        }

        $seen[$key] = true;

        [$product, $variant, $suggestedBy] = $this->resolveTarget($buyer, $columns, $values, $code);

        if ($product === null) {
            $row['action'] = 'unmatched';
            $row['message'] = 'No product matches our_sku / variant_sku, GTIN or manufacturer part number.';

            return $row;
        }

        $row['product_sku'] = $product->sku;
        $row['variant_sku'] = $variant?->sku;
        $row['suggested_by'] = $suggestedBy;

        $explicitSku = $value('our_sku') !== '' || $value('variant_sku') !== '';

        if (! $explicitSku && ! $applySuggestions) {
            $row['action'] = 'suggested';
            $row['message'] = sprintf(
                'Suggested by %s. Add our_sku or send apply_suggestions=true to accept.',
                $suggestedBy ?? 'a previous mapping',
            );

            return $row;
        }

        $item = BuyerItem::query()
            ->where('buyer_party_id', $buyer->buyer_party_id)
            ->where('code_key', $key)
            ->first();

        $mapping = $item === null
            ? null
            : BuyerItemMapping::query()
                ->where('tenant_id', $buyer->tenant_id)
                ->where('buyer_item_id', $item->id)
                ->first();

        if ($mapping !== null) {
            $sameTarget = $mapping->product_id === $product->id
                && (int) ($mapping->variant_id ?? 0) === (int) ($variant?->id ?? 0);

            if ($sameTarget) {
                $row['action'] = 'unchanged';
                $row['message'] = 'Already mapped to this product.';

                return $row;
            }

            if (! $remap) {
                $row['action'] = 'conflict';
                $row['message'] = 'Already mapped to another product. Send remap=true to change it.';

                return $row;
            }

            $row['action'] = 'remap';
        } else {
            $row['action'] = 'create';
        }

        $row['_payload'] = [
            'buyer_part_number' => $code,
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'short_description' => $value('short_description') ?: null,
            'long_description' => $value('long_description') ?: null,
            'unit_of_measure' => $value('unit_of_measure') ?: null,
            'manufacturer_part_number' => $value('manufacturer_part_number') ?: null,
            'gtin' => $value('gtin') ?: null,
            'is_primary' => $this->boolean($value('is_primary'), true),
            'effective_from' => $value('effective_from') ?: null,
            'effective_to' => $value('effective_to') ?: null,
            'source' => 'import',
            'remap' => $row['action'] === 'remap',
        ];

        return $row;
    }

    /**
     * @param  array<string, int>  $columns
     * @param  array<int, string|null>  $values
     * @return array{0: Product|null, 1: Variant|null, 2: string|null}
     */
    private function resolveTarget(
        PunchoutBuyer $buyer,
        array $columns,
        array $values,
        string $code,
    ): array {
        $value = fn (string $column): string => trim((string) $this->cell($columns, $values, $column));

        $sku = $value('our_sku');
        $variantSku = $value('variant_sku');

        if ($variantSku !== '') {
            $variant = Variant::query()
                ->with('product')
                ->where('tenant_id', $buyer->tenant_id)
                ->where('sku', $variantSku)
                ->first();

            if ($variant?->product) {
                return [$variant->product, $variant, 'variant_sku'];
            }
        }

        if ($sku !== '') {
            $product = Product::query()
                ->where('tenant_id', $buyer->tenant_id)
                ->where('sku', $sku)
                ->first();

            return [$product, null, $product ? 'our_sku' : null];
        }

        $gtin = $value('gtin');

        if ($gtin !== '') {
            $product = Product::query()
                ->where('tenant_id', $buyer->tenant_id)
                ->where('gtin', $gtin)
                ->first();

            if ($product) {
                return [$product, null, 'gtin'];
            }
        }

        $mpn = $value('manufacturer_part_number');

        if ($mpn !== '') {
            $product = Product::query()
                ->where('tenant_id', $buyer->tenant_id)
                ->where(fn ($query) => $query
                    ->where('model_number', $mpn)
                    ->orWhere('metadata->manufacturer_part_number', $mpn))
                ->first();

            if ($product) {
                return [$product, null, 'manufacturer_part_number'];
            }
        }

        // Last resort: the code is already mapped for this tenant.
        $existing = BuyerItemMapping::query()
            ->with(['product', 'variant'])
            ->where('tenant_id', $buyer->tenant_id)
            ->where('buyer_party_id', $buyer->buyer_party_id)
            ->whereHas('item', fn ($query) => $query->where('code_key', BuyerItem::keyFor($code)))
            ->first();

        if ($existing?->product) {
            return [$existing->product, $existing->variant, 'existing_mapping'];
        }

        return [null, null, null];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $report
     */
    private function applyChunk(PunchoutBuyer $buyer, array $rows, array &$report): void
    {
        DB::transaction(function () use ($buyer, $rows, &$report): void {
            foreach ($rows as $row) {
                try {
                    $this->buyerItemMappingService->map($buyer, $row['_payload']);
                    $report['totals']['applied']++;
                } catch (ConflictHttpException $exception) {
                    $report['totals'][$row['action']]--;
                    $report['totals']['conflict']++;
                    $this->replaceReportRow($report, $row['line'], 'conflict', $exception->getMessage());
                } catch (ValidationException $exception) {
                    $report['totals'][$row['action']]--;
                    $report['totals']['invalid']++;
                    $this->replaceReportRow($report, $row['line'], 'invalid', $exception->getMessage());
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function replaceReportRow(array &$report, int $line, string $action, string $message): void
    {
        foreach ($report['rows'] as $index => $existing) {
            if ($existing['line'] === $line) {
                $report['rows'][$index]['action'] = $action;
                $report['rows'][$index]['message'] = $message;

                return;
            }
        }
    }

    /**
     * @param  array<string, int>  $columns
     * @param  array<int, string|null>  $values
     */
    private function cell(array $columns, array $values, string $column): ?string
    {
        $position = $columns[$column] ?? null;

        return $position === null ? null : ($values[$position] ?? null);
    }

    private function boolean(string $value, bool $default): bool
    {
        if ($value === '') {
            return $default;
        }

        return in_array(mb_strtolower($value), ['1', 'true', 'yes', 'y'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function newReport(bool $dryRun): array
    {
        return [
            'dry_run' => $dryRun,
            'totals' => [
                'rows' => 0,
                'create' => 0,
                'remap' => 0,
                'unchanged' => 0,
                'suggested' => 0,
                'conflict' => 0,
                'unmatched' => 0,
                'invalid' => 0,
                'duplicate' => 0,
                'applied' => 0,
            ],
            'rows' => [],
            'truncated' => false,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function templateColumns(): array
    {
        return [
            'buyer_part_number',
            'our_sku',
            'variant_sku',
            'short_description',
            'long_description',
            'unit_of_measure',
            'manufacturer_part_number',
            'gtin',
            'is_primary',
            'effective_from',
            'effective_to',
        ];
    }
}
