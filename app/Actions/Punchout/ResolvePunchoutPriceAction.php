<?php

namespace App\Actions\Punchout;

use App\Models\Price;
use App\Models\Product;
use App\Models\PunchoutBuyer;
use App\Models\Tenant;
use App\Models\Variant;
use Illuminate\Support\Collection;

class ResolvePunchoutPriceAction
{
    /**
     * Resolve the price a buyer sees for a product/variant at a quantity.
     *
     * Precedence (highest first):
     *   1. the buyer's contract (variant beats product, then volume tier);
     *   2. the contract's catalog;
     *   3. the seller's tenant price list for the product;
     *   4. the product/variant metadata price.
     *
     * Prices are always resolved server-side; the punchout UI never supplies
     * one. Contract rows that belong to a different buyer are ignored.
     *
     * @return array<string, mixed>
     */
    public function handle(
        Tenant $tenant,
        Product $product,
        ?Variant $variant = null,
        float|int $quantity = 1,
        ?PunchoutBuyer $buyer = null,
        bool $forDisplay = false,
    ): array {
        $quantity = max(1, (float) $quantity);
        $candidates = $this->fetchCandidates($tenant, [$product->id], Product::class, $quantity, $forDisplay);

        if ($variant !== null) {
            $candidates = $candidates->concat(
                $this->fetchCandidates($tenant, [$variant->id], Variant::class, $quantity, $forDisplay),
            );
        }

        return $this->resolve($candidates, $tenant, $product, $variant, $buyer, $forDisplay);
    }

    /**
     * Resolve product-level prices for a catalog page in one query.
     *
     * @param  iterable<Product>  $products
     * @return array<int, array<string, mixed>>
     */
    public function resolveForProducts(Tenant $tenant, ?PunchoutBuyer $buyer, iterable $products): array
    {
        $products = collect($products)->keyBy('id');

        if ($products->isEmpty()) {
            return [];
        }

        $candidates = $this->fetchCandidates(
            $tenant,
            $products->keys()->all(),
            Product::class,
            1,
            allTiers: true,
        )->groupBy(fn (Price $price) => (int) $price->priceable_id);

        $resolved = [];

        foreach ($products as $product) {
            $resolved[$product->id] = $this->resolve(
                $candidates->get($product->id, collect()),
                $tenant,
                $product,
                null,
                $buyer,
                forDisplay: true,
            );
        }

        return $resolved;
    }

    /**
     * Resolve variant-level prices for a product detail page.
     *
     * @param  iterable<Variant>  $variants
     * @return array<int, array<string, mixed>>
     */
    public function resolveForVariants(
        Tenant $tenant,
        ?PunchoutBuyer $buyer,
        Product $product,
        iterable $variants,
        int|float $quantity = 1,
    ): array {
        $productCandidates = $this->fetchCandidates(
            $tenant,
            [$product->id],
            Product::class,
            max(1, (float) $quantity),
            allTiers: true,
        );
        $resolved = [];

        foreach ($variants as $variant) {
            $variantCandidates = $this->fetchCandidates(
                $tenant,
                [$variant->id],
                Variant::class,
                max(1, (float) $quantity),
                allTiers: true,
            );

            $resolved[$variant->id] = $this->resolve(
                $productCandidates->concat($variantCandidates),
                $tenant,
                $product,
                $variant,
                $buyer,
                forDisplay: true,
            );
        }

        return $resolved;
    }

    /**
     * @param  Collection<int, Price>  $candidates
     * @return array<string, mixed>
     */
    private function resolve(
        Collection $candidates,
        Tenant $tenant,
        Product $product,
        ?Variant $variant,
        ?PunchoutBuyer $buyer,
        bool $forDisplay = false,
    ): array {
        $preferredCurrency = $this->preferredCurrency($tenant, $buyer);
        $contract = $buyer?->contract;
        $scored = [];

        foreach ($candidates as $price) {
            $score = $this->score($price, $product, $variant, $buyer, $contract, $preferredCurrency);

            if ($score === null) {
                continue;
            }

            $effective = $this->effectivePrice($price);

            $scored[] = [
                'price' => $price,
                'score' => $score,
                'effective' => $effective,
                'base' => (float) $price->base_price,
                'sale' => (float) $price->sale_price,
            ];
        }

        if ($scored !== []) {
            usort($scored, function (array $a, array $b) use ($forDisplay): int {
                // Cart resolution wants the highest applicable tier; a catalog
                // card shows the entry (lowest-quantity) tier as a "from" price.
                $tierComparison = $forDisplay
                    ? ((float) $a['price']->min_quantity <=> (float) $b['price']->min_quantity)
                    : ((float) $b['price']->min_quantity <=> (float) $a['price']->min_quantity);

                return $b['score'] <=> $a['score']
                    ?: ((int) $b['price']->priority <=> (int) $a['price']->priority)
                    ?: $tierComparison
                    ?: ($a['effective'] <=> $b['effective']);
            });

            $best = $scored[0];
            /** @var Price $price */
            $price = $best['price'];
            $metadata = is_array($price->metadata) ? $price->metadata : [];

            return [
                'unit_price' => round((float) $best['effective'], 2),
                'base_price' => $best['base'] > 0 ? round($best['base'], 2) : null,
                'sale_price' => ($best['sale'] > 0 && $best['sale'] < $best['base'])
                    ? round($best['sale'], 2)
                    : null,
                'currency' => strtoupper((string) ($price->currency?->code ?? $preferredCurrency)),
                'currency_id' => $price->currency_id,
                'source' => $this->source($price, $contract, $product),
                'price_id' => $price->id,
                'contract_id' => $price->contract_id,
                'min_quantity' => (float) $price->min_quantity,
                'customer_part_number' => $this->stringOrNull(data_get($metadata, 'customer_part_number')),
                'unit_of_measure' => $this->stringOrNull(data_get($metadata, 'unit_of_measure'))
                    ?? $this->stringOrNull($price->unit?->code),
                'lead_time_days' => data_get($metadata, 'lead_time_days'),
            ];
        }

        return $this->metadataFallback($product, $variant, $preferredCurrency);
    }

    /**
     * @param  array<int, int|string>  $priceableIds
     * @return Collection<int, Price>
     */
    private function fetchCandidates(
        Tenant $tenant,
        array $priceableIds,
        string $priceableType,
        float $quantity,
        bool $allTiers = false,
    ): Collection {
        if ($priceableIds === []) {
            return collect();
        }

        return Price::query()
            ->with(['currency', 'unit'])
            ->where('tenant_id', $tenant->id)
            ->where('priceable_type', $priceableType)
            ->whereIn('priceable_id', $priceableIds)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->when(! $allTiers, function ($query) use ($quantity): void {
                $query->where('min_quantity', '<=', $quantity)
                    ->where(function ($query) use ($quantity): void {
                        $query->whereNull('max_quantity')->orWhere('max_quantity', '>=', $quantity);
                    });
            })
            ->get();
    }

    private function score(
        Price $price,
        Product $product,
        ?Variant $variant,
        ?PunchoutBuyer $buyer,
        mixed $contract,
        string $preferredCurrency,
    ): ?int {
        $score = 0;
        $isVariant = $price->priceable_type === Variant::class;

        if ($isVariant) {
            if ($variant === null || (int) $price->priceable_id !== (int) $variant->id) {
                return null;
            }

            $score += 50;
        } else {
            if ((int) $price->priceable_id !== (int) $product->id) {
                return null;
            }

            $score += 40;
        }

        if ($price->contract_id !== null) {
            if ($buyer?->contract_id === null || (int) $price->contract_id !== (int) $buyer->contract_id) {
                return null;
            }

            $score += 100;
        }

        if ($price->catalog_id !== null) {
            $contractCatalogId = $contract?->catalog_id;

            if ($contractCatalogId === null || (int) $price->catalog_id !== (int) $contractCatalogId) {
                return null;
            }

            $score += 60;
        }

        if ($price->contract_id === null && $price->catalog_id === null) {
            $score += 10;

            if ((int) $price->organization_id === (int) $product->organization_id) {
                $score += 5;
            }
        }

        $currencyCode = strtoupper((string) ($price->currency?->code ?? ''));

        if ($preferredCurrency !== '' && $currencyCode === $preferredCurrency) {
            $score += 20;
        }

        return $score;
    }

    private function effectivePrice(Price $price): float
    {
        $base = (float) $price->base_price;
        $sale = (float) $price->sale_price;

        return $sale > 0 && $sale < $base ? $sale : $base;
    }

    private function source(Price $price, mixed $contract, Product $product): string
    {
        if ($price->contract_id !== null) {
            return 'contract';
        }

        if ($price->catalog_id !== null && $contract?->catalog_id === $price->catalog_id) {
            return 'catalog';
        }

        if ((int) $price->organization_id === (int) $product->organization_id) {
            return 'price_list';
        }

        return 'tenant';
    }

    private function preferredCurrency(Tenant $tenant, ?PunchoutBuyer $buyer): string
    {
        $currency = $buyer?->default_currency
            ?: data_get(is_array($tenant->settings) ? $tenant->settings : [], 'punchout.default_currency', 'USD');

        return strtoupper((string) ($currency ?: 'USD'));
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataFallback(Product $product, ?Variant $variant, string $preferredCurrency): array
    {
        $metadata = $variant?->metadata ?? $product->metadata ?? [];
        $metadata = is_array($metadata) ? $metadata : [];

        $base = (float) data_get($metadata, 'price', 0);
        $sale = (float) data_get($metadata, 'sale_price', 0);
        $effective = ($sale > 0 && $sale < $base) ? $sale : $base;

        return [
            'unit_price' => round($effective, 2),
            'base_price' => $base > 0 ? round($base, 2) : null,
            'sale_price' => ($sale > 0 && $sale < $base) ? round($sale, 2) : null,
            'currency' => strtoupper((string) (data_get($metadata, 'currency') ?: $preferredCurrency)),
            'currency_id' => null,
            'source' => 'product_metadata',
            'price_id' => null,
            'contract_id' => null,
            'min_quantity' => 1.0,
            'customer_part_number' => $this->stringOrNull(data_get($metadata, 'customer_part_number')),
            'unit_of_measure' => $this->stringOrNull(data_get($metadata, 'unit_of_measure')) ?? 'EA',
            'lead_time_days' => data_get($metadata, 'lead_time_days'),
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
