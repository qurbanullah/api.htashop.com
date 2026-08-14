<?php

namespace Tests\Unit\Price;

test('the Price action classes exist', function () {
    expect(class_exists(\App\Actions\Price\PriceCreateAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Price\PriceDeleteAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Price\PriceReadAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Price\PriceSearchByIdAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Price\PriceUpdateAction::class))->toBeTrue();
});
