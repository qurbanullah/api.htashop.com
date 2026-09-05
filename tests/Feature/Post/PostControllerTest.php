<?php

namespace Tests\Feature\Post;

test('the Post controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Post\AdminPostController::class))->toBeTrue();
    expect(class_exists(\App\Http\Controllers\V1\Post\PostApiController::class))->toBeTrue();
});
