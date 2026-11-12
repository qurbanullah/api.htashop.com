<?php

namespace Tests\Feature\Value;

use App\Http\Controllers\V1\Value\ValueController;

test('the Value controller class exists', function () {
    expect(class_exists(ValueController::class))->toBeTrue();
});
