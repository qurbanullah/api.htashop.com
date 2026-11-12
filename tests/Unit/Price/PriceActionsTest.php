<?php

namespace Tests\Unit\Price;

use App\Actions\Price\PriceCreateAction;
use App\Actions\Price\PriceDeleteAction;
use App\Actions\Price\PriceReadAction;
use App\Actions\Price\PriceSearchByIdAction;
use App\Actions\Price\PriceUpdateAction;

test('the Price action classes exist', function () {
    expect(class_exists(PriceCreateAction::class))->toBeTrue();
    expect(class_exists(PriceDeleteAction::class))->toBeTrue();
    expect(class_exists(PriceReadAction::class))->toBeTrue();
    expect(class_exists(PriceSearchByIdAction::class))->toBeTrue();
    expect(class_exists(PriceUpdateAction::class))->toBeTrue();
});
