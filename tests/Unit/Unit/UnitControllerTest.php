<?php

namespace Tests\Unit\Unit;

test('the Unit controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Unit\UnitController::class))->toBeTrue();
});
