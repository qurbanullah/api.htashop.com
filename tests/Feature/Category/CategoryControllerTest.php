<?php

namespace Tests\Feature\Category;

use App\Http\Controllers\V1\Category\CategoryController;

test('the Category controller class exists', function () {
    expect(class_exists(CategoryController::class))->toBeTrue();
});
