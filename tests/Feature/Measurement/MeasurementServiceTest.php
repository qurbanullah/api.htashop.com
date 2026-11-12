<?php

namespace Tests\Feature\Measurement;

use App\Services\Measurement\MeasurementService;

test('the Measurement service classes exist', function () {
    expect(class_exists(MeasurementService::class))->toBeTrue();
});
