<?php

namespace Tests\Unit\EmailLog;

use App\Http\Controllers\V1\Email\EmailLogController;

test('the EmailLog controller class exists', function () {
    expect(class_exists(EmailLogController::class))->toBeTrue();
});
