<?php

namespace Tests\Feature\ForumComment;

test('the ForumComment service classes exist', function () {
    expect(class_exists(\App\Services\Forum\ForumCommentService::class))->toBeTrue();
});
