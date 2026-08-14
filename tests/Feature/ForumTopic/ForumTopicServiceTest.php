<?php

namespace Tests\Feature\ForumTopic;

test('the ForumTopic service classes exist', function () {
    expect(class_exists(\App\Services\Forum\ForumTopicService::class))->toBeTrue();
});
