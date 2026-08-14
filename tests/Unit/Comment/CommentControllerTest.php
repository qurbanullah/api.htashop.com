<?php

namespace Tests\Unit\Comment;

test('the Comment controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Comment\CommentController::class))->toBeTrue();
    expect(class_exists(\App\Http\Controllers\V1\Forum\PublicForumCommentController::class))->toBeTrue();
});
