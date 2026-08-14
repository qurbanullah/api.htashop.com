<?php

namespace Tests\Feature\Value;

test('the Value controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Value\ValueController::class))->toBeTrue();
});
