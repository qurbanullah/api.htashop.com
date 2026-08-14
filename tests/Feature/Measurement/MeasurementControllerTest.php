<?php

namespace Tests\Feature\Measurement;

test('the Measurement controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Measurement\MeasurementController::class))->toBeTrue();
});
