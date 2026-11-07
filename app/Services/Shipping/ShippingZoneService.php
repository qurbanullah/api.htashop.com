<?php

namespace App\Services\Shipping;

use App\Models\City;
use App\Models\ShippingZone;
use App\Models\ShippingZoneLocation;
use App\Services\Shipping\Concerns\ResolvesShippingScope;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ShippingZoneService
{
    use ResolvesShippingScope;

    private const RELATIONS = ['tenant', 'locations.country', 'locations.city'];

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ShippingZone>
     */
    public function read(array $filters = []): LengthAwarePaginator
    {
        $user = auth()->user();

        $query = ShippingZone::query()->with(self::RELATIONS);

        if ($user?->isAdmin() && data_get($filters, 'tenant_id') !== null) {
            $query->where('tenant_id', (int) data_get($filters, 'tenant_id'));
        }

        $this->applyReadScope($query, $user);

        return $query
            ->when(data_get($filters, 'is_active'), fn ($q) => $q->where('is_active', true))
            ->when(data_get($filters, 'search'), fn ($q, $search) => $q->where(
                fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"),
            ))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate((int) data_get($filters, 'per_page', 100));
    }

    public function searchByUuid(string $uuid): ShippingZone
    {
        return ShippingZone::query()
            ->with(self::RELATIONS)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ShippingZone
    {
        $locations = data_get($data, 'locations', []);
        $data['tenant_id'] = $this->resolveWriteTenant($data, auth()->user());
        unset($data['locations']);

        return DB::transaction(function () use ($data, $locations) {
            $zone = ShippingZone::query()->create($data);
            $this->syncLocations($zone, $locations);

            return $zone->load(self::RELATIONS);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ShippingZone $zone, array $data): ShippingZone
    {
        $user = auth()->user();
        $hasLocations = array_key_exists('locations', $data);
        $locations = data_get($data, 'locations', []);

        if (! $user?->isAdmin()) {
            unset($data['tenant_id']);
        }

        unset($data['locations']);

        return DB::transaction(function () use ($zone, $data, $hasLocations, $locations) {
            $zone->fill($data)->save();

            if ($hasLocations) {
                $this->syncLocations($zone, $locations);
            }

            return $zone->fresh(self::RELATIONS);
        });
    }

    public function delete(ShippingZone $zone): bool
    {
        return (bool) $zone->delete();
    }

    /**
     * Replace the zone's matchers. Irrelevant columns are nulled so a location
     * only ever carries the fields its type means.
     *
     * @param  array<int, array<string, mixed>>  $locations
     */
    private function syncLocations(ShippingZone $zone, array $locations): void
    {
        $zone->locations()->delete();

        foreach ($locations as $location) {
            $type = (string) data_get($location, 'type', ShippingZoneLocation::TYPE_COUNTRY);
            $cityId = $type === ShippingZoneLocation::TYPE_CITY
                ? data_get($location, 'city_id')
                : null;
            $countryId = data_get($location, 'country_id');

            if ($cityId !== null && $countryId === null) {
                $countryId = City::query()->whereKey($cityId)->value('country_id');
            }

            $zone->locations()->create([
                'type' => $type,
                'country_id' => $countryId,
                'city_id' => $cityId,
                'state' => $type === ShippingZoneLocation::TYPE_STATE
                    ? data_get($location, 'state')
                    : null,
                'postal_code_prefix' => $type === ShippingZoneLocation::TYPE_POSTAL_PREFIX
                    ? data_get($location, 'postal_code_prefix')
                    : null,
            ]);
        }
    }
}
