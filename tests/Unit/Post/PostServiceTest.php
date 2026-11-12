<?php

namespace Tests\Unit\Post;

use App\Services\Post\PostService;

test('the Post service classes exist', function () {
    expect(class_exists(PostService::class))->toBeTrue();
});
