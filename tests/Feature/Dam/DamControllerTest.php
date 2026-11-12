<?php

namespace Tests\Feature\Dam;

use App\Http\Controllers\V1\Dam\DamCollectionController;
use App\Http\Controllers\V1\Dam\DamController;

test('the Dam controller class exists', function () {
    expect(class_exists(DamCollectionController::class))->toBeTrue();
    expect(class_exists(DamController::class))->toBeTrue();
});
