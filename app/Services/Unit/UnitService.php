<?php

namespace App\Services\Unit;

use App\Models\Unit;
use Illuminate\Pagination\LengthAwarePaginator;

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
        return Unit::create($data);
    }

    public function update(Unit $unit, array $data): Unit
    {
        $unit->update($data);
        return $unit->fresh();
    }

    public function delete(Unit $unit): bool
    {
        return $unit->delete() ?? false;
    }
}
