<?php

namespace Tests\Feature\Category;

test('the Category controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Category\CategoryController::class))->toBeTrue();
});
