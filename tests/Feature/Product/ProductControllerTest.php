<?php

namespace Tests\Feature\Product;

use App\Http\Controllers\V1\Product\ProductController;

test('the Product controller class exists', function () {
    expect(class_exists(ProductController::class))->toBeTrue();
});
