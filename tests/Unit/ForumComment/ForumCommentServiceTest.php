<?php

namespace Tests\Unit\ForumComment;

use App\Services\Forum\ForumCommentService;

test('the ForumComment service classes exist', function () {
    expect(class_exists(ForumCommentService::class))->toBeTrue();
});
