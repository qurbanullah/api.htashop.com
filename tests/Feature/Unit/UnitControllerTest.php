<?php

namespace Tests\Feature\Unit;

test('the Unit controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Unit\UnitController::class))->toBeTrue();
});
