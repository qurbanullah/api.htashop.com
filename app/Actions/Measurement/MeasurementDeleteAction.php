<?php

namespace App\Actions\Measurement;

use App\Models\Measurement;

class MeasurementDeleteAction
{
    public function handle(Measurement $measurement): bool
    {
        return (bool) $measurement->delete();
    }
}
