<?php

namespace Tests\Feature\ForumComment;

test('the ForumComment controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Forum\PublicForumCommentController::class))->toBeTrue();
});
