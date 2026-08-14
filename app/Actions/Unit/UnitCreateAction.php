<?php

namespace App\Actions\Unit;

use App\Models\Unit;
use Illuminate\Support\Str;

class UnitCreateAction
{
    public function handle(array $data): Unit
    {
        return Unit::create([
            'tenant_id' => data_get($data, 'tenant_id'),
            'measurement_id' => data_get($data, 'measurement_id'),
            'name' => data_get($data, 'name'),
            'code' => $this->resolveCode(data_get($data, 'code'), data_get($data, 'name'), data_get($data, 'measurement_id')),
            'symbol' => data_get($data, 'symbol'),
            'factor' => data_get($data, 'factor', 1),
            'offset' => data_get($data, 'offset', 0),
            'precision' => data_get($data, 'precision', 2),
            'is_active' => data_get($data, 'is_active', true),
        ]);
    }

    private function resolveCode(?string $code, string $name, int $measurementId): string
    {
        $candidate = Str::slug($code ?: $name, '_');
        $base = $candidate;
        $suffix = 1;

        while (Unit::query()->where('measurement_id', $measurementId)->where('code', $candidate)->exists()) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
