<?php

namespace Tests\Feature\Product;

use App\Actions\Product\ProductCreateAction;
use App\Actions\Product\ProductDeleteAction;
use App\Actions\Product\ProductReadAction;
use App\Actions\Product\ProductSearchByUuidAction;
use App\Actions\Product\ProductUpdateAction;

test('the Product action classes exist', function () {
    expect(class_exists(ProductCreateAction::class))->toBeTrue();
    expect(class_exists(ProductDeleteAction::class))->toBeTrue();
    expect(class_exists(ProductReadAction::class))->toBeTrue();
    expect(class_exists(ProductSearchByUuidAction::class))->toBeTrue();
    expect(class_exists(ProductUpdateAction::class))->toBeTrue();
});
