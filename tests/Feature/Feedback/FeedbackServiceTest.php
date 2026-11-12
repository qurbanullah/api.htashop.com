<?php

namespace Tests\Feature\Feedback;

use App\Services\Feedbacks\FeedbackService;

test('the Feedback service classes exist', function () {
    expect(class_exists(FeedbackService::class))->toBeTrue();
});
