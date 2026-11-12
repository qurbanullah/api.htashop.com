<?php

namespace Tests\Unit\Comment;

use App\Services\Comment\CommentService;
use App\Services\Forum\ForumCommentService;

test('the Comment service classes exist', function () {
    expect(class_exists(CommentService::class))->toBeTrue();
    expect(class_exists(ForumCommentService::class))->toBeTrue();
});
