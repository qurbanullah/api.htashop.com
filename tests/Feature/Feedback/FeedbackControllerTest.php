<?php

namespace Tests\Feature\Feedback;

use App\Http\Controllers\V1\Feedback\FeedbackAdminController;
use App\Http\Controllers\V1\Feedback\FeedbackController;

test('the Feedback controller class exists', function () {
    expect(class_exists(FeedbackAdminController::class))->toBeTrue();
    expect(class_exists(FeedbackController::class))->toBeTrue();
});
