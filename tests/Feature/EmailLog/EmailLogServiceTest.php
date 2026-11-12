<?php

namespace Tests\Feature\EmailLog;

use App\Services\Email\EmailLogService;

test('the EmailLog service classes exist', function () {
    expect(class_exists(EmailLogService::class))->toBeTrue();
});
