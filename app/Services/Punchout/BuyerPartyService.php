<?php

namespace App\Services\Punchout;

use App\Models\BuyerItem;
use App\Models\BuyerItemMapping;
use App\Models\BuyerParty;
use App\Models\PunchoutBuyer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuyerPartyService
{
    public function read(array $filters = []): LengthAwarePaginator
    {
        $query = BuyerParty::query()->withCount(['buyers', 'items']);

        if ($term = data_get($filters, 'q')) {
            $query->where(function ($query) use ($term): void {
                $query->where('name', 'like', '%'.$term.'%')
                    ->orWhere('network_id', 'like', '%'.$term.'%')
                    ->orWhere('duns', 'like', '%'.$term.'%');
            });
        }

        $hasBuyers = data_get($filters, 'has_buyers');

        if ($hasBuyers !== null) {
            (bool) $hasBuyers ? $query->has('buyers') : $query->doesntHave('buyers');
        }

        return $query->orderBy('name')->paginate((int) data_get($filters, 'per_page', 15));
    }

    public function find(string $uuid): BuyerParty
    {
        return BuyerParty::query()
            ->with(['buyers:id,uuid,tenant_id,name,code,buyer_party_id'])
            ->withCount(['buyers', 'items'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function create(array $data): BuyerParty
    {
        return BuyerParty::query()->create([
            'name' => data_get($data, 'name'),
            'network_id' => data_get($data, 'network_id'),
            'duns' => data_get($data, 'duns'),
            'country' => data_get($data, 'country'),
            'metadata' => data_get($data, 'metadata'),
        ]);
    }

    public function update(BuyerParty $party, array $data): BuyerParty
    {
        $party->fill([
            'name' => data_get($data, 'name', $party->name),
            'network_id' => array_key_exists('network_id', $data) ? data_get($data, 'network_id') : $party->network_id,
            'duns' => array_key_exists('duns', $data) ? data_get($data, 'duns') : $party->duns,
            'country' => array_key_exists('country', $data) ? data_get($data, 'country') : $party->country,
            'metadata' => array_key_exists('metadata', $data) ? data_get($data, 'metadata') : $party->metadata,
        ]);

        $party->save();

        return $party->refresh();
    }

    /**
     * Link tenant relationships to one party.
     *
     * A buyer that already points at a different party is merged: items move,
     * colliding codes collapse into the target item, and mappings are repointed
     * (or dropped when the tenant already mapped the target item). A dry run
     * performs the same work inside a rolled-back transaction so the report is
     * exact rather than estimated.
     *
     * @param  array<int, string>  $buyerUuids
     * @return array<string, mixed>
     */
    public function link(BuyerParty $target, array $buyerUuids, bool $dryRun = false): array
    {
        $buyers = PunchoutBuyer::query()->whereIn('uuid', $buyerUuids)->get();
        $missing = array_values(array_diff($buyerUuids, $buyers->pluck('uuid')->all()));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'buyer_uuids' => ['Unknown buyer(s): '.implode(', ', $missing)],
            ]);
        }

        $report = [
            'dry_run' => $dryRun,
            'target' => $target->uuid,
            'linked' => [],
            'merged_parties' => [],
            'items_moved' => 0,
            'items_merged' => 0,
            'mappings_moved' => 0,
            'mappings_dropped' => 0,
        ];

        $apply = function () use ($target, $buyers, &$report): void {
            foreach ($buyers as $buyer) {
                $buyer->refresh();

                if ($buyer->buyer_party_id === $target->id) {
                    $report['linked'][] = $buyer->uuid;

                    continue;
                }

                if ($buyer->buyer_party_id === null) {
                    $buyer->forceFill(['buyer_party_id' => $target->id])->save();
                    $report['linked'][] = $buyer->uuid;

                    continue;
                }

                $source = BuyerParty::query()->find($buyer->buyer_party_id);

                if ($source !== null && $source->id !== $target->id) {
                    $this->merge($target, $source, $report);
                }

                $report['linked'][] = $buyer->uuid;
            }
        };

        if ($dryRun) {
            DB::beginTransaction();

            try {
                $apply();
            } finally {
                DB::rollBack();
            }
        } else {
            DB::transaction($apply);
        }

        return $report;
    }

    /**
     * Move a source party's item master and mapping rows onto the target party.
     *
     * @param  array<string, mixed>  $report
     */
    private function merge(BuyerParty $target, BuyerParty $source, array &$report): void
    {
        $source->load('items.mappings');

        foreach ($source->items as $sourceItem) {
            $targetItem = BuyerItem::query()
                ->where('buyer_party_id', $target->id)
                ->where('code_key', $sourceItem->code_key)
                ->first();

            if ($targetItem === null) {
                $sourceItem->forceFill(['buyer_party_id' => $target->id])->save();
                BuyerItemMapping::query()
                    ->where('buyer_item_id', $sourceItem->id)
                    ->update(['buyer_party_id' => $target->id]);

                $report['items_moved']++;

                continue;
            }

            foreach ($sourceItem->mappings as $mapping) {
                $existing = BuyerItemMapping::query()
                    ->where('tenant_id', $mapping->tenant_id)
                    ->where('buyer_item_id', $targetItem->id)
                    ->first();

                if ($existing !== null) {
                    if ($mapping->is_primary && ! $existing->is_primary) {
                        $existing->forceFill(['is_primary' => true])->save();
                    }

                    $mapping->delete();
                    $report['mappings_dropped']++;

                    continue;
                }

                $mapping->forceFill([
                    'buyer_item_id' => $targetItem->id,
                    'buyer_party_id' => $target->id,
                ])->save();

                $report['mappings_moved']++;
            }

            $sourceItem->delete();
            $report['items_merged']++;
        }

        PunchoutBuyer::query()
            ->where('buyer_party_id', $source->id)
            ->update(['buyer_party_id' => $target->id]);

        $source->delete();
        $report['merged_parties'][] = $source->uuid;
    }
}
