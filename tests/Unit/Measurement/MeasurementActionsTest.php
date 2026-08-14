<?php

namespace Tests\Unit\Measurement;

test('the Measurement action classes exist', function () {
    expect(class_exists(\App\Actions\Measurement\MeasurementCreateAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Measurement\MeasurementDeleteAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Measurement\MeasurementReadAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Measurement\MeasurementSearchByUuidAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Measurement\MeasurementUpdateAction::class))->toBeTrue();
});
