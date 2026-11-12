<?php

namespace Tests\Unit\Measurement;

use App\Http\Controllers\V1\Measurement\MeasurementController;

test('the Measurement controller class exists', function () {
    expect(class_exists(MeasurementController::class))->toBeTrue();
});
