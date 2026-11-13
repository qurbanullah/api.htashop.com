<?php

namespace App\Support\Shipping;

use App\Models\Measurement;
use App\Models\Unit;

/**
 * Converts a value from the editor's chosen unit into a canonical unit of the
 * same measurement, mirroring `ValueCreateAction::normalizeNumber()`:
 *
 *     base = (value + offset) * factor
 *     target = base / target.factor
 *
 * Weights normalise to kilograms and dimensions to centimetres, which is what
 * the rate engine and the storefront both consume. When the requested code is
 * absent from the catalogue we fall back to the measurement's declared
 * `base_unit_id`, so conversion still works for custom UoM sets.
 */
class UnitConverter
{
    public static function toKilograms(?float $value, ?int $unitId): ?float
    {
        return self::convert($value, $unitId, 'kg');
    }

    public static function toCentimetres(?float $value, ?int $unitId): ?float
    {
        return self::convert($value, $unitId, 'cm');
    }

    public static function convert(?float $value, ?int $unitId, string $targetCode): ?float
    {
        if ($value === null || $unitId === null) {
            return null;
        }

        $unit = Unit::query()->find($unitId);

        if (! $unit instanceof Unit) {
            return null;
        }

        $target = self::resolveTarget($unit, $targetCode);

        if (! $target instanceof Unit) {
            return null;
        }

        $targetFactor = (float) $target->factor;

        if ($targetFactor === 0.0) {
            return null;
        }

        $base = ((float) $value + (float) $unit->offset) * (float) $unit->factor;

        return round($base / $targetFactor, 3);
    }

    /**
     * Prefer the requested code (canonical kg/cm), then the declared base unit.
     */
    private static function resolveTarget(Unit $unit, string $targetCode): ?Unit
    {
        $target = Unit::query()
            ->where('measurement_id', $unit->measurement_id)
            ->where('code', $targetCode)
            ->first();

        if ($target instanceof Unit) {
            return $target;
        }

        $baseUnitId = Measurement::query()
            ->whereKey($unit->measurement_id)
            ->value('base_unit_id');

        if ($baseUnitId === null) {
            return null;
        }

        return Unit::query()
            ->where('measurement_id', $unit->measurement_id)
            ->whereKey($baseUnitId)
            ->first();
    }
}
