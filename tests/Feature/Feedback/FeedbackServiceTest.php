<?php

namespace Tests\Feature\Feedback;

test('the Feedback service classes exist', function () {
    expect(class_exists(\App\Services\Feedbacks\FeedbackService::class))->toBeTrue();
});
