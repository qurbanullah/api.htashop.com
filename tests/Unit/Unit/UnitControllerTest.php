<?php

namespace Tests\Unit\Unit;

use App\Http\Controllers\V1\Unit\UnitController;

test('the Unit controller class exists', function () {
    expect(class_exists(UnitController::class))->toBeTrue();
});
