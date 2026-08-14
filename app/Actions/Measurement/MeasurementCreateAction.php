<?php

namespace App\Actions\Measurement;

use App\Models\Measurement;
use Illuminate\Support\Str;

class MeasurementCreateAction
{
    public function handle(array $data): Measurement
    {
        return Measurement::create([
            'tenant_id' => data_get($data, 'tenant_id'),
            'name' => data_get($data, 'name'),
            'code' => $this->resolveCode(data_get($data, 'code'), data_get($data, 'name'), data_get($data, 'tenant_id')),
            'description' => data_get($data, 'description'),
            'is_active' => data_get($data, 'is_active', true),
            'metadata' => data_get($data, 'metadata'),
        ]);
    }

    private function resolveCode(?string $code, string $name, int $tenantId): string
    {
        $candidate = Str::slug($code ?: $name, '_');
        $base = $candidate;
        $suffix = 1;

        while (Measurement::query()->where('tenant_id', $tenantId)->where('code', $candidate)->exists()) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
