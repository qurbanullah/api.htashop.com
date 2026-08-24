<?php

namespace App\Services\Unit;

use App\Models\Unit;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class UnitService
{
    public function read(array $filters = []): LengthAwarePaginator
    {
        return Unit::query()
            ->with('measurement')
            ->when(data_get($filters, 'measurement_id'), fn ($q, $id) => $q->where('measurement_id', $id))
            ->when(data_get($filters, 'measurement_code'), fn ($q, $code) => $q->whereHas('measurement', fn ($mq) => $mq->where('code', $code)))
            ->when(data_get($filters, 'is_active'), fn ($q) => $q->where('is_active', true))
            ->orderBy('measurement_id')
            ->orderBy('name')
            ->paginate((int) data_get($filters, 'per_page', 500));
    }

    public function searchByUuid(string $uuid): Unit
    {
        return Unit::where('uuid', $uuid)->with('measurement')->firstOrFail();
    }

    public function create(array $data): Unit
    {
        $data['code'] = $this->resolveCode(
            data_get($data, 'code'),
            data_get($data, 'name'),
            (int) data_get($data, 'measurement_id'),
        );

        return Unit::create($data);
    }

    public function update(Unit $unit, array $data): Unit
    {
        $data['code'] = $this->resolveCode(
            data_get($data, 'code'),
            data_get($data, 'name', $unit->name),
            (int) data_get($data, 'measurement_id', $unit->measurement_id),
            $unit->id,
        );

        $unit->update($data);

        return $unit->fresh();
    }

    public function delete(Unit $unit): bool
    {
        return $unit->delete() ?? false;
    }

    /**
     * Derive a unique code from the provided value or the name.
     * Codes are unique per measurement (units.measurement_id + code).
     */
    private function resolveCode(?string $code, ?string $name, int $measurementId, ?int $ignoreId = null): string
    {
        $candidate = Str::slug($code ?: $name ?: '', '_') ?: 'code';
        $base = $candidate;
        $suffix = 1;

        while (Unit::query()
            ->where('measurement_id', $measurementId)
            ->where('code', $candidate)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
