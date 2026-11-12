<?php

namespace Tests\Unit\Post;

use App\Http\Controllers\V1\Post\AdminPostController;
use App\Http\Controllers\V1\Post\PostApiController;

test('the Post controller class exists', function () {
    expect(class_exists(AdminPostController::class))->toBeTrue();
    expect(class_exists(PostApiController::class))->toBeTrue();
});
