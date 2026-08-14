<?php

namespace Tests\Feature\Product;

test('the Product action classes exist', function () {
    expect(class_exists(\App\Actions\Product\ProductCreateAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Product\ProductDeleteAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Product\ProductReadAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Product\ProductSearchByUuidAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Product\ProductUpdateAction::class))->toBeTrue();
});
