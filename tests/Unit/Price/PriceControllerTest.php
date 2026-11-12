<?php

namespace Tests\Unit\Price;

use App\Http\Controllers\V1\Price\PriceController;

test('the Price controller class exists', function () {
    expect(class_exists(PriceController::class))->toBeTrue();
});
