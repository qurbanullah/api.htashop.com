<?php

namespace Tests\Unit\Dam;

test('the Dam controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Dam\DamCollectionController::class))->toBeTrue();
    expect(class_exists(\App\Http\Controllers\V1\Dam\DamController::class))->toBeTrue();
});
