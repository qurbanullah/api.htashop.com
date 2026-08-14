<?php

namespace Tests\Feature\ForumPost;

test('the ForumPost service classes exist', function () {
    expect(class_exists(\App\Services\Forum\ForumPostService::class))->toBeTrue();
});
