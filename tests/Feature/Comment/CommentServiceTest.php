<?php

namespace Tests\Feature\Comment;

test('the Comment service classes exist', function () {
    expect(class_exists(\App\Services\Comment\CommentService::class))->toBeTrue();
    expect(class_exists(\App\Services\Forum\ForumCommentService::class))->toBeTrue();
});
