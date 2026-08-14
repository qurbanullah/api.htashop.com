<?php

namespace Tests\Unit\Price;

test('the Price service classes exist', function () {
    expect(class_exists(\App\Services\Price\PriceService::class))->toBeTrue();
});
