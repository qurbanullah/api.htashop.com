<?php

namespace App\Services\Punchout;

use App\Actions\Punchout\EnsureBuyerPartyAction;
use App\Models\BuyerItemMapping;
use App\Models\PunchoutBuyer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * CRUD over one buyer relationship's part-number mappings.
 *
 * Everything here is scoped to the buyer's tenant and party, so the admin and
 * manager APIs can share it: the controller only decides how the buyer was
 * resolved, never how the rows are filtered.
 */
class BuyerPartNumberService
{
    public function __construct(
        protected BuyerItemMappingService $buyerItemMappingService,
        protected EnsureBuyerPartyAction $ensureBuyerPartyAction,
    ) {}

    public function read(PunchoutBuyer $buyer, array $filters = []): LengthAwarePaginator
    {
        $this->ensureBuyerPartyAction->handle($buyer);

        return BuyerItemMapping::query()
            ->with(['item', 'product', 'variant'])
            ->where('tenant_id', $buyer->tenant_id)
            ->where('buyer_party_id', $buyer->buyer_party_id)
            ->when(data_get($filters, 'q'), function ($query, string $term): void {
                $query->whereHas('item', function ($itemQuery) use ($term): void {
                    $itemQuery->where('code', 'like', '%'.$term.'%')
                        ->orWhere('code_key', 'like', '%'.$term.'%')
                        ->orWhere('short_description', 'like', '%'.$term.'%')
                        ->orWhere('long_description', 'like', '%'.$term.'%');
                });
            })
            ->when(data_get($filters, 'product_uuid'), fn ($query, string $uuid) => $query->whereHas(
                'product',
                fn ($productQuery) => $productQuery->where('uuid', $uuid),
            ))
            ->when(data_get($filters, 'product_id'), fn ($query, $productId) => $query->where('product_id', (int) $productId))
            ->when(data_get($filters, 'is_active') !== null, fn ($query) => $query->where('is_active', (bool) data_get($filters, 'is_active')))
            ->orderByDesc('is_primary')
            ->orderByDesc('id')
            ->paginate((int) data_get($filters, 'per_page', 15));
    }

    public function find(PunchoutBuyer $buyer, string $uuid): BuyerItemMapping
    {
        return BuyerItemMapping::query()
            ->with(['item', 'product', 'variant'])
            ->where('tenant_id', $buyer->tenant_id)
            ->where('buyer_party_id', $buyer->buyer_party_id)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function create(PunchoutBuyer $buyer, array $data): BuyerItemMapping
    {
        $result = $this->buyerItemMappingService->map($buyer, $data);

        return $result['mapping']->load(['item', 'product', 'variant']);
    }

    public function update(PunchoutBuyer $buyer, BuyerItemMapping $mapping, array $data): BuyerItemMapping
    {
        $party = $this->ensureBuyerPartyAction->handle($buyer);

        $product = array_key_exists('product_id', $data)
            ? $this->buyerItemMappingService->productFor($buyer, (int) $data['product_id'])
            : $mapping->product;

        $variant = array_key_exists('variant_id', $data)
            ? $this->buyerItemMappingService->variantFor($buyer, $product, $data['variant_id'])
            : ($mapping->product_id === $product->id ? $mapping->variant : null);

        $code = array_key_exists('buyer_part_number', $data)
            ? trim((string) $data['buyer_part_number'])
            : (string) $mapping->item->code;

        if ($code === '') {
            throw ValidationException::withMessages([
                'buyer_part_number' => ['A buyer part number is required.'],
            ]);
        }

        [$item] = $this->buyerItemMappingService->resolveItem($party, $code, $data);

        if ($item->id !== $mapping->buyer_item_id) {
            $conflict = BuyerItemMapping::query()
                ->where('tenant_id', $buyer->tenant_id)
                ->where('buyer_item_id', $item->id)
                ->whereKeyNot($mapping->id)
                ->exists();

            if ($conflict) {
                throw new ConflictHttpException(sprintf(
                    'Buyer part number %s is already mapped to another product for this buyer.',
                    $item->code,
                ));
            }
        }

        $mapping->fill([
            'buyer_party_id' => $party->id,
            'buyer_item_id' => $item->id,
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'is_primary' => (bool) data_get($data, 'is_primary', $mapping->is_primary),
            'is_active' => (bool) data_get($data, 'is_active', $mapping->is_active),
            'effective_from' => array_key_exists('effective_from', $data) ? data_get($data, 'effective_from') : $mapping->effective_from,
            'effective_to' => array_key_exists('effective_to', $data) ? data_get($data, 'effective_to') : $mapping->effective_to,
            'metadata' => array_key_exists('metadata', $data) ? data_get($data, 'metadata') : $mapping->metadata,
        ]);

        $mapping->save();
        $this->buyerItemMappingService->demoteOtherPrimaries($mapping);

        return $mapping->refresh()->load(['item', 'product', 'variant']);
    }

    public function delete(BuyerItemMapping $mapping): void
    {
        $mapping->delete();
    }
}
