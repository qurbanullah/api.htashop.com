<?php

namespace Tests\Feature\Measurement;

test('the Measurement service classes exist', function () {
    expect(class_exists(\App\Services\Measurement\MeasurementService::class))->toBeTrue();
});
