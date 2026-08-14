<?php

namespace Tests\Unit\Category;

use App\Models\Category;
use Illuminate\Database\Eloquent\Model;

test('the Category model class exists', function () {
    expect(class_exists(Category::class))->toBeTrue();
    expect(is_subclass_of(Category::class, Model::class))->toBeTrue();
});
