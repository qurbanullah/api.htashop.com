<?php

namespace Tests\Feature\ForumComment;

use App\Http\Controllers\V1\Forum\PublicForumCommentController;

test('the ForumComment controller class exists', function () {
    expect(class_exists(PublicForumCommentController::class))->toBeTrue();
});
