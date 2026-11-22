<?php

namespace App\Console\Commands\Punchout;

use App\Models\BuyerItem;
use App\Models\BuyerItemMapping;
use App\Models\Price;
use App\Models\Product;
use App\Models\PunchoutBuyer;
use App\Models\Tenant;
use App\Models\Variant;
use App\Services\Punchout\BuyerItemMappingService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Moves legacy `prices.metadata.customer_part_number` values into the buyer
 * item master.
 *
 * Only prices scoped to a contract or a catalog can be attributed to a buyer;
 * global price rows are reported as ambiguous rather than guessed. Safe to run
 * repeatedly: existing mappings come back as unchanged.
 */
class BackfillBuyerPartNumbersCommand extends Command
{
    protected $signature = 'punchout:backfill-buyer-part-numbers {--dry-run : Classify without writing} {--tenant= : Tenant uuid or slug}';

    protected $description = 'Copy price-level customer part numbers into buyer items and tenant mappings';

    private const AMBIGUOUS = 'ambiguous';

    private const NO_BUYER = 'no_buyer';

    private const NO_PARTY = 'no_party';

    private const CONFLICT = 'conflict';

    private const CREATED = 'created';

    private const UNCHANGED = 'unchanged';

    private const REMAPPED = 'remapped';

    public function handle(BuyerItemMappingService $buyerItemMappingService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $query = $this->priceQuery();

        if ($query === null) {
            return self::FAILURE;
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('No price rows carry a customer part number.');

            return self::SUCCESS;
        }

        $stats = array_fill_keys([
            self::CREATED, self::UNCHANGED, self::REMAPPED,
            self::CONFLICT, self::AMBIGUOUS, self::NO_BUYER, self::NO_PARTY,
        ], 0);

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(200, function ($prices) use ($buyerItemMappingService, $dryRun, &$stats, $bar): void {
            foreach ($prices as $price) {
                $this->classifyPrice($price, $buyerItemMappingService, $dryRun, $stats);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->table(['Result', 'Count'], collect($stats)->map(fn ($count, $key) => [$key, $count])->values()->all());
        $this->line($dryRun ? 'Dry run: nothing was written.' : 'Backfill complete.');

        return self::SUCCESS;
    }

    private function priceQuery(): ?Builder
    {
        $query = Price::query()
            ->with('priceable')
            ->whereNotNull('metadata')
            ->whereIn('priceable_type', [Product::class, Variant::class])
            ->where('is_active', true)
            ->whereRaw("JSON_EXTRACT(metadata, '$.customer_part_number') IS NOT NULL");

        $tenantOption = trim((string) $this->option('tenant'));

        if ($tenantOption === '') {
            return $query;
        }

        $tenant = Tenant::query()
            ->where('uuid', $tenantOption)
            ->orWhere('slug', $tenantOption)
            ->first();

        if ($tenant === null) {
            $this->error("Tenant '{$tenantOption}' was not found.");

            return null;
        }

        return $query->where('tenant_id', $tenant->id);
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function classifyPrice(Price $price, BuyerItemMappingService $mapper, bool $dryRun, array &$stats): void
    {
        $code = trim((string) data_get($price->metadata, 'customer_part_number', ''));

        if ($code === '') {
            return;
        }

        [$product, $variant] = $this->targetFor($price);

        if ($product === null) {
            return;
        }

        $buyers = $this->buyersFor($price);

        if ($buyers === null) {
            $stats[self::AMBIGUOUS]++;

            return;
        }

        if ($buyers->isEmpty()) {
            $stats[self::NO_BUYER]++;

            return;
        }

        foreach ($buyers as $buyer) {
            if ($dryRun && $buyer->buyer_party_id === null) {
                $stats[self::NO_PARTY]++;

                continue;
            }

            if ($dryRun) {
                $stats[$this->dryRunOutcome($buyer, $code, $product, $variant)]++;

                continue;
            }

            try {
                $result = $mapper->map($buyer, [
                    'buyer_part_number' => $code,
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'source' => 'backfill',
                ]);

                $stats[$this->mapOutcome($result['action'])]++;
            } catch (ConflictHttpException) {
                $stats[self::CONFLICT]++;
            }
        }
    }

    /**
     * @return array{0: Product|null, 1: Variant|null}
     */
    private function targetFor(Price $price): array
    {
        $priceable = $price->priceable;

        if ($priceable instanceof Variant && $priceable->product) {
            return [$priceable->product, $priceable];
        }

        if ($priceable instanceof Product) {
            return [$priceable, null];
        }

        return [null, null];
    }

    /**
     * @return Collection<int, PunchoutBuyer>|null Null when the
     *                                             price row is not scoped to a buyer.
     */
    private function buyersFor(Price $price): ?Collection
    {
        $query = PunchoutBuyer::query()
            ->where('tenant_id', $price->tenant_id)
            ->where('is_active', true);

        if ($price->contract_id !== null) {
            return $query->where('contract_id', $price->contract_id)->get();
        }

        if ($price->catalog_id !== null) {
            return $query->whereHas('contract', fn ($contract) => $contract->where('catalog_id', $price->catalog_id))->get();
        }

        return null;
    }

    private function dryRunOutcome(PunchoutBuyer $buyer, string $code, Product $product, ?Variant $variant): string
    {
        $item = BuyerItem::query()
            ->where('buyer_party_id', $buyer->buyer_party_id)
            ->where('code_key', BuyerItem::keyFor($code))
            ->first();

        if ($item === null) {
            return self::CREATED;
        }

        $mapping = BuyerItemMapping::query()
            ->where('tenant_id', $buyer->tenant_id)
            ->where('buyer_item_id', $item->id)
            ->first();

        if ($mapping === null) {
            return self::CREATED;
        }

        $same = $mapping->product_id === $product->id
            && (int) ($mapping->variant_id ?? 0) === (int) ($variant?->id ?? 0);

        return $same ? self::UNCHANGED : self::CONFLICT;
    }

    private function mapOutcome(string $action): string
    {
        return match ($action) {
            'created' => self::CREATED,
            'remapped' => self::REMAPPED,
            default => self::UNCHANGED,
        };
    }
}
