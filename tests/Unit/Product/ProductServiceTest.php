<?php

namespace Tests\Unit\Product;

test('the Product service classes exist', function () {
    expect(class_exists(\App\Services\Product\ProductService::class))->toBeTrue();
});
