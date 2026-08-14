<?php

namespace App\Actions\Measurement;

use App\Models\Measurement;

class MeasurementSearchByUuidAction
{
    public function handle(string $uuid): Measurement
    {
        return Measurement::query()->where('uuid', $uuid)->firstOrFail();
    }
}
