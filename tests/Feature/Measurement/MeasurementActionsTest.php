<?php

namespace Tests\Feature\Measurement;

use App\Actions\Measurement\MeasurementCreateAction;
use App\Actions\Measurement\MeasurementDeleteAction;
use App\Actions\Measurement\MeasurementReadAction;
use App\Actions\Measurement\MeasurementSearchByUuidAction;
use App\Actions\Measurement\MeasurementUpdateAction;

test('the Measurement action classes exist', function () {
    expect(class_exists(MeasurementCreateAction::class))->toBeTrue();
    expect(class_exists(MeasurementDeleteAction::class))->toBeTrue();
    expect(class_exists(MeasurementReadAction::class))->toBeTrue();
    expect(class_exists(MeasurementSearchByUuidAction::class))->toBeTrue();
    expect(class_exists(MeasurementUpdateAction::class))->toBeTrue();
});
