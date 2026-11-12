<?php

namespace Tests\Unit\ForumPost;

use App\Services\Forum\ForumPostService;

test('the ForumPost service classes exist', function () {
    expect(class_exists(ForumPostService::class))->toBeTrue();
});
