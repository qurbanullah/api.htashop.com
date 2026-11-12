<?php

namespace Tests\Unit\Price;

use App\Services\Price\PriceService;

test('the Price service classes exist', function () {
    expect(class_exists(PriceService::class))->toBeTrue();
});
