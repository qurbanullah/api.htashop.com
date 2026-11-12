<?php

namespace Tests\Feature\ForumTopic;

use App\Services\Forum\ForumTopicService;

test('the ForumTopic service classes exist', function () {
    expect(class_exists(ForumTopicService::class))->toBeTrue();
});
