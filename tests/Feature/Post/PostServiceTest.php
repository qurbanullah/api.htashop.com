<?php

namespace Tests\Feature\Post;

test('the Post service classes exist', function () {
    expect(class_exists(\App\Services\Post\PostService::class))->toBeTrue();
});
