<?php

namespace Tests\Feature\Product;

use App\Services\Product\ProductService;

test('the Product service classes exist', function () {
    expect(class_exists(ProductService::class))->toBeTrue();
});
