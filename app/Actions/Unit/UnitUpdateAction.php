<?php

namespace App\Actions\Unit;

use App\Models\Unit;
use Illuminate\Support\Str;

class UnitUpdateAction
{
    public function handle(Unit $unit, array $data): Unit
    {
        $measurementId = (int) (array_key_exists('measurement_id', $data) ? data_get($data, 'measurement_id') : $unit->measurement_id);

        $unit->update([
            'tenant_id' => data_get($data, 'tenant_id', $unit->tenant_id),
            'measurement_id' => $measurementId,
            'name' => data_get($data, 'name', $unit->name),
            'code' => $this->resolveCode($unit, data_get($data, 'code'), data_get($data, 'name', $unit->name), $measurementId),
            'symbol' => array_key_exists('symbol', $data) ? data_get($data, 'symbol') : $unit->symbol,
            'factor' => data_get($data, 'factor', $unit->factor),
            'offset' => data_get($data, 'offset', $unit->offset),
            'precision' => data_get($data, 'precision', $unit->precision),
            'is_active' => data_get($data, 'is_active', $unit->is_active),
        ]);

        return $unit->refresh()->load('measurement');
    }

    private function resolveCode(Unit $unit, ?string $code, string $name, int $measurementId): string
    {
        $candidate = Str::slug($code ?: $name, '_');
        $base = $candidate;
        $suffix = 1;

        while (Unit::query()->where('measurement_id', $measurementId)->where('code', $candidate)->where('id', '!=', $unit->id)->exists()) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
