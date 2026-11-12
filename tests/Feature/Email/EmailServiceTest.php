<?php

namespace Tests\Feature\Email;

use App\Services\Email\EmailLogService;

test('the Email service classes exist', function () {
    expect(class_exists(EmailLogService::class))->toBeTrue();
});
