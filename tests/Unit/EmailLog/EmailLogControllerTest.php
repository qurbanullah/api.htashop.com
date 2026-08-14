<?php

namespace Tests\Unit\EmailLog;

test('the EmailLog controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Email\EmailLogController::class))->toBeTrue();
});
