<?php

namespace App\Services\Punchout;

use App\Actions\Punchout\EnsureBuyerPartyAction;
use App\Models\BuyerItem;
use App\Models\BuyerItemMapping;
use App\Models\BuyerParty;
use App\Models\Product;
use App\Models\PunchoutBuyer;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Creates buyer items and the tenant's mapping to one of its own products.
 *
 * The buyer item is shared by every tenant selling to that buyer: a second
 * tenant importing the same code reuses the item and adds only its mapping.
 * A conflict only ever means this tenant already maps the code elsewhere.
 */
class BuyerItemMappingService
{
    /** Fields the buyer owns; a tenant may fill blanks but not overwrite them. */
    private const ITEM_FIELDS = [
        'short_description',
        'long_description',
        'unit_of_measure',
        'manufacturer_part_number',
        'gtin',
        'unspsc',
    ];

    public function __construct(
        protected EnsureBuyerPartyAction $ensureBuyerPartyAction,
    ) {}

    /**
     * @return array{item: BuyerItem, mapping: BuyerItemMapping, created_item: bool, action: string}
     */
    public function map(PunchoutBuyer $buyer, array $data): array
    {
        $party = $this->ensureBuyerPartyAction->handle($buyer);
        $product = $this->productFor($buyer, (int) data_get($data, 'product_id'));
        $variant = $this->variantFor($buyer, $product, data_get($data, 'variant_id'));

        $code = trim((string) data_get($data, 'buyer_part_number'));

        if ($code === '') {
            throw ValidationException::withMessages([
                'buyer_part_number' => ['A buyer part number is required.'],
            ]);
        }

        return DB::transaction(function () use ($buyer, $party, $product, $variant, $code, $data): array {
            [$item, $createdItem] = $this->resolveItem($party, $code, $data);

            $existing = BuyerItemMapping::query()
                ->where('tenant_id', $buyer->tenant_id)
                ->where('buyer_item_id', $item->id)
                ->first();

            if ($existing !== null) {
                return $this->handleExisting($existing, $product, $variant, $item, $data, $createdItem);
            }

            $mapping = BuyerItemMapping::query()->create([
                'tenant_id' => $buyer->tenant_id,
                'buyer_party_id' => $party->id,
                'buyer_item_id' => $item->id,
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'is_primary' => (bool) data_get($data, 'is_primary', true),
                'is_active' => (bool) data_get($data, 'is_active', true),
                'effective_from' => data_get($data, 'effective_from'),
                'effective_to' => data_get($data, 'effective_to'),
                'source' => (string) data_get($data, 'source', 'manual'),
                'metadata' => data_get($data, 'metadata'),
            ]);

            $this->demoteOtherPrimaries($mapping);

            return [
                'item' => $item,
                'mapping' => $mapping,
                'created_item' => $createdItem,
                'action' => 'created',
            ];
        });
    }

    /**
     * @return array{0: BuyerItem, 1: bool}
     */
    public function resolveItem(BuyerParty $party, string $code, array $data = []): array
    {
        $key = BuyerItem::keyFor($code);

        $item = BuyerItem::query()
            ->where('buyer_party_id', $party->id)
            ->where('code_key', $key)
            ->first();

        $created = $item === null;

        if ($created) {
            $item = new BuyerItem([
                'buyer_party_id' => $party->id,
                'code' => $code,
                'code_key' => $key,
                'source' => (string) data_get($data, 'source', 'manual'),
            ]);
        }

        // Never overwrite a value the buyer already published; only fill blanks.
        foreach (self::ITEM_FIELDS as $field) {
            $incoming = data_get($data, $field);

            if ($item->{$field} === null && $incoming !== null && $incoming !== '') {
                $item->{$field} = $incoming;
            }
        }

        if ($item->isDirty() || $created) {
            $item->save();
        }

        return [$item, $created];
    }

    private function handleExisting(
        BuyerItemMapping $existing,
        Product $product,
        ?Variant $variant,
        BuyerItem $item,
        array $data,
        bool $createdItem,
    ): array {
        $sameTarget = $existing->product_id === $product->id
            && (int) ($existing->variant_id ?? 0) === (int) ($variant?->id ?? 0);

        $wantsPrimary = (bool) data_get($data, 'is_primary', $existing->is_primary);

        if ($sameTarget) {
            if ($wantsPrimary !== $existing->is_primary) {
                $existing->forceFill(['is_primary' => $wantsPrimary])->save();
                $this->demoteOtherPrimaries($existing);
            }

            return [
                'item' => $item,
                'mapping' => $existing,
                'created_item' => $createdItem,
                'action' => 'unchanged',
            ];
        }

        if (! (bool) data_get($data, 'remap', false)) {
            throw new ConflictHttpException(sprintf(
                'Buyer part number %s is already mapped to product %s for this buyer. Send remap=true to change it.',
                $existing->item?->code ?? (string) $item->code,
                $existing->product_id,
            ));
        }

        $existing->fill([
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'buyer_party_id' => $item->buyer_party_id,
            'is_primary' => $wantsPrimary,
            'effective_from' => data_get($data, 'effective_from', $existing->effective_from),
            'effective_to' => data_get($data, 'effective_to', $existing->effective_to),
            'is_active' => (bool) data_get($data, 'is_active', $existing->is_active),
            'metadata' => data_get($data, 'metadata', $existing->metadata),
        ]);

        $existing->save();
        $this->demoteOtherPrimaries($existing);

        return [
            'item' => $item,
            'mapping' => $existing,
            'created_item' => $createdItem,
            'action' => 'remapped',
        ];
    }

    public function productFor(PunchoutBuyer $buyer, int $productId): Product
    {
        $product = Product::query()
            ->where('tenant_id', $buyer->tenant_id)
            ->whereKey($productId)
            ->first();

        if ($product === null) {
            throw ValidationException::withMessages([
                'product_id' => ['The selected product does not belong to this tenant.'],
            ]);
        }

        return $product;
    }

    public function variantFor(PunchoutBuyer $buyer, Product $product, mixed $variantId): ?Variant
    {
        if ($variantId === null || $variantId === '') {
            return null;
        }

        $variant = Variant::query()
            ->where('tenant_id', $buyer->tenant_id)
            ->where('product_id', $product->id)
            ->whereKey((int) $variantId)
            ->first();

        if ($variant === null) {
            throw ValidationException::withMessages([
                'variant_id' => ['The selected variant does not belong to this product.'],
            ]);
        }

        return $variant;
    }

    /**
     * Only one mapping may be primary per (tenant, product, variant), so the
     * cart always has a single code to emit.
     */
    public function demoteOtherPrimaries(BuyerItemMapping $mapping): void
    {
        if (! $mapping->is_primary) {
            return;
        }

        BuyerItemMapping::query()
            ->where('tenant_id', $mapping->tenant_id)
            ->where('product_id', $mapping->product_id)
            ->whereKeyNot($mapping->id)
            ->where(function ($query) use ($mapping): void {
                if ($mapping->variant_id === null) {
                    $query->whereNull('variant_id');
                } else {
                    $query->where('variant_id', $mapping->variant_id);
                }
            })
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}
