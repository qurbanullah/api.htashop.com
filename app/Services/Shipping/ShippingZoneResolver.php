<?php

namespace App\Services\Shipping;

use App\Models\ShippingZone;
use App\Models\ShippingZoneLocation;

/**
 * Picks the most specific active zone for a destination.
 *
 * A blanket country location matches everything; a city or postal-prefix
 * location overrides it. When two zones tie, the lower `sort_order` wins, then
 * the older row, so the result is deterministic.
 */
class ShippingZoneResolver
{
    /**
     * @param  array<string, mixed>|null  $destination  Keys: country_id, city_id,
     *                                                  state, state_code, postal_code
     */
    public function resolve(?array $destination, ?int $tenantId = null): ?ShippingZone
    {
        $countryId = data_get($destination, 'country_id');

        if ($countryId === null || $countryId === '') {
            return null;
        }

        $countryId = (int) $countryId;
        $cityId = (int) data_get($destination, 'city_id', 0);
        $state = $this->normalise(data_get($destination, 'state'));
        $stateCode = $this->normalise(data_get($destination, 'state_code'));
        $postalCode = $this->normalisePostal(data_get($destination, 'postal_code'));

        $zones = ShippingZone::query()
            ->where('is_active', true)
            ->where(function ($query) use ($tenantId) {
                $query->whereNull('tenant_id');

                if ($tenantId !== null) {
                    $query->orWhere('tenant_id', $tenantId);
                }
            })
            ->with('locations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $best = null;
        $bestScore = 0;

        foreach ($zones as $zone) {
            $score = $this->score($zone, $countryId, $cityId, $state, $stateCode, $postalCode);

            if ($score > $bestScore) {
                $best = $zone;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function score(
        ShippingZone $zone,
        int $countryId,
        int $cityId,
        string $state,
        string $stateCode,
        string $postalCode,
    ): int {
        $best = 0;

        foreach ($zone->locations as $location) {
            if ($location->country_id !== null && (int) $location->country_id !== $countryId) {
                continue;
            }

            $best = max($best, match ($location->type) {
                ShippingZoneLocation::TYPE_CITY => $this->cityScore($location, $cityId),
                ShippingZoneLocation::TYPE_POSTAL_PREFIX => $this->postalScore($location, $postalCode),
                ShippingZoneLocation::TYPE_STATE => $this->stateScore($location, $state, $stateCode),
                ShippingZoneLocation::TYPE_COUNTRY => 100,
                default => 0,
            });
        }

        return $best;
    }

    private function cityScore(ShippingZoneLocation $location, int $cityId): int
    {
        return $cityId > 0 && (int) $location->city_id === $cityId ? 400 : 0;
    }

    private function postalScore(ShippingZoneLocation $location, string $postalCode): int
    {
        $prefix = $this->normalisePostal($location->postal_code_prefix);

        if ($postalCode === '' || $prefix === '' || ! str_starts_with($postalCode, $prefix)) {
            return 0;
        }

        // A longer prefix is a tighter match.
        return 300 + strlen($prefix);
    }

    private function stateScore(ShippingZoneLocation $location, string $state, string $stateCode): int
    {
        $expected = $this->normalise($location->state);

        if ($expected === '') {
            return 0;
        }

        if ($expected === $state || $expected === $stateCode) {
            return 200;
        }

        return 0;
    }

    private function normalise(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    /** Postal matching ignores spaces so "54 570" and "54570" agree. */
    private function normalisePostal(mixed $value): string
    {
        return preg_replace('/\s+/', '', (string) $value) ?? '';
    }
}
