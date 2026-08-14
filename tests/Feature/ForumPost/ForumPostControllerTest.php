<?php

namespace Tests\Feature\ForumPost;

test('the ForumPost controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Forum\ForumPostController::class))->toBeTrue();
});
