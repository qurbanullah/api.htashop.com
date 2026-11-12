<?php

namespace Tests\Unit\Comment;

use App\Http\Controllers\V1\Comment\CommentController;
use App\Http\Controllers\V1\Forum\PublicForumCommentController;

test('the Comment controller class exists', function () {
    expect(class_exists(CommentController::class))->toBeTrue();
    expect(class_exists(PublicForumCommentController::class))->toBeTrue();
});
