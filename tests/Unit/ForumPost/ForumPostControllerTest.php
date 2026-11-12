<?php

namespace Tests\Unit\ForumPost;

use App\Http\Controllers\V1\Forum\ForumPostController;

test('the ForumPost controller class exists', function () {
    expect(class_exists(ForumPostController::class))->toBeTrue();
});
