<?php

namespace Tests\Feature\Product;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

test('the Product model class exists', function () {
    expect(class_exists(Product::class))->toBeTrue();
    expect(is_subclass_of(Product::class, Model::class))->toBeTrue();
});
