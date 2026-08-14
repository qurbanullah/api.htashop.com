<?php

namespace Tests\Feature\Feedback;

test('the Feedback controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Feedback\FeedbackAdminController::class))->toBeTrue();
    expect(class_exists(\App\Http\Controllers\V1\Feedback\FeedbackController::class))->toBeTrue();
});
