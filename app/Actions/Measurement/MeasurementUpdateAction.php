<?php

namespace App\Actions\Measurement;

use App\Models\Measurement;
use Illuminate\Support\Str;

class MeasurementUpdateAction
{
    public function handle(Measurement $measurement, array $data): Measurement
    {
        $measurement->update([
            'tenant_id' => data_get($data, 'tenant_id', $measurement->tenant_id),
            'name' => data_get($data, 'name', $measurement->name),
            'code' => $this->resolveCode($measurement, data_get($data, 'code'), data_get($data, 'name', $measurement->name), data_get($data, 'tenant_id', $measurement->tenant_id)),
            'description' => array_key_exists('description', $data) ? data_get($data, 'description') : $measurement->description,
            'is_active' => data_get($data, 'is_active', $measurement->is_active),
            'metadata' => array_key_exists('metadata', $data) ? data_get($data, 'metadata') : $measurement->metadata,
        ]);

        return $measurement->refresh();
    }

    private function resolveCode(Measurement $measurement, ?string $code, string $name, int $tenantId): string
    {
        $candidate = Str::slug($code ?: $name, '_');
        $base = $candidate;
        $suffix = 1;

        while (Measurement::query()->where('tenant_id', $tenantId)->where('code', $candidate)->where('id', '!=', $measurement->id)->exists()) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
