<?php

namespace Tests\Unit\Product;

test('the Product controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Product\ProductController::class))->toBeTrue();
});
