<?php

namespace Tests\Unit\Email;

use App\Http\Controllers\V1\Email\EmailController;
use App\Http\Controllers\V1\Email\EmailLogController;

test('the Email controller class exists', function () {
    expect(class_exists(EmailController::class))->toBeTrue();
    expect(class_exists(EmailLogController::class))->toBeTrue();
});
