<?php

namespace App\Services\Measurement;

use App\Actions\Measurement\MeasurementCreateAction;
use App\Actions\Measurement\MeasurementDeleteAction;
use App\Actions\Measurement\MeasurementReadAction;
use App\Actions\Measurement\MeasurementSearchByUuidAction;
use App\Actions\Measurement\MeasurementUpdateAction;
use App\Models\Measurement;
use Illuminate\Pagination\LengthAwarePaginator;

class MeasurementService
{
    public function create(array $data): Measurement
    {
        return (new MeasurementCreateAction())->handle($data);
    }

    public function read(array $filters = []): LengthAwarePaginator
    {
        return (new MeasurementReadAction())->handle($filters);
    }

    public function searchByUuid(string $uuid): Measurement
    {
        return (new MeasurementSearchByUuidAction())->handle($uuid);
    }

    public function update(Measurement $measurement, array $data): Measurement
    {
        return (new MeasurementUpdateAction())->handle($measurement, $data);
    }

    public function delete(Measurement $measurement): bool
    {
        return (new MeasurementDeleteAction())->handle($measurement);
    }
}
