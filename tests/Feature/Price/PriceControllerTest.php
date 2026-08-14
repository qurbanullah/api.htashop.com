<?php

namespace Tests\Feature\Price;

test('the Price controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Price\PriceController::class))->toBeTrue();
});
