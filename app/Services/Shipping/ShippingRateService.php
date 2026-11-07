<?php

namespace App\Services\Shipping;

use App\Models\ShippingRate;
use App\Services\Shipping\Concerns\ResolvesShippingScope;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ShippingRateService
{
    use ResolvesShippingScope;

    private const RELATIONS = ['tenant', 'zone', 'warehouse', 'tiers'];

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ShippingRate>
     */
    public function read(array $filters = []): LengthAwarePaginator
    {
        $user = auth()->user();

        $query = ShippingRate::query()->with(self::RELATIONS);

        if ($user?->isAdmin() && data_get($filters, 'tenant_id') !== null) {
            $query->where('tenant_id', (int) data_get($filters, 'tenant_id'));
        }

        $this->applyReadScope($query, $user);

        return $query
            ->when(data_get($filters, 'shipping_zone_id'), fn ($q, $zoneId) => $q->where('shipping_zone_id', $zoneId))
            ->when(data_get($filters, 'currency'), fn ($q, $currency) => $q->where('currency', strtoupper((string) $currency)))
            ->when(data_get($filters, 'is_active'), fn ($q) => $q->where('is_active', true))
            ->when(data_get($filters, 'search'), fn ($q, $search) => $q->where(
                fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"),
            ))
            ->orderBy('priority')
            ->orderBy('name')
            ->paginate((int) data_get($filters, 'per_page', 100));
    }

    public function searchByUuid(string $uuid): ShippingRate
    {
        return ShippingRate::query()
            ->with(self::RELATIONS)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ShippingRate
    {
        $tiers = data_get($data, 'tiers', []);
        $data['tenant_id'] = $this->resolveWriteTenant($data, auth()->user());
        $data['currency'] = strtoupper((string) data_get($data, 'currency', 'PKR'));
        $data['carrier'] = data_get($data, 'carrier') ?: ShippingRate::CARRIER_MANUAL;
        unset($data['tiers']);

        return DB::transaction(function () use ($data, $tiers) {
            $rate = ShippingRate::query()->create($data);
            $this->syncTiers($rate, $tiers);

            return $rate->load(self::RELATIONS);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ShippingRate $rate, array $data): ShippingRate
    {
        $user = auth()->user();
        $hasTiers = array_key_exists('tiers', $data);
        $tiers = data_get($data, 'tiers', []);

        if (! $user?->isAdmin()) {
            unset($data['tenant_id']);
        }

        if (array_key_exists('currency', $data)) {
            $data['currency'] = strtoupper((string) $data['currency']);
        }

        if (array_key_exists('carrier', $data)) {
            $data['carrier'] = $data['carrier'] ?: ShippingRate::CARRIER_MANUAL;
        }

        unset($data['tiers']);

        return DB::transaction(function () use ($rate, $data, $hasTiers, $tiers) {
            $rate->fill($data)->save();

            if ($hasTiers) {
                $this->syncTiers($rate, $tiers);
            }

            return $rate->fresh(self::RELATIONS);
        });
    }

    public function delete(ShippingRate $rate): bool
    {
        return (bool) $rate->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     */
    private function syncTiers(ShippingRate $rate, array $tiers): void
    {
        $rate->tiers()->delete();

        if ($rate->calculation !== ShippingRate::CALC_TIERED) {
            return;
        }

        foreach ($tiers as $tier) {
            $rate->tiers()->create([
                'min_weight_kg' => data_get($tier, 'min_weight_kg', 0),
                'max_weight_kg' => data_get($tier, 'max_weight_kg'),
                'amount' => data_get($tier, 'amount', 0),
            ]);
        }
    }
}
