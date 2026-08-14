<?php

namespace Tests\Feature\Email;

test('the Email service classes exist', function () {
    expect(class_exists(\App\Services\Email\EmailLogService::class))->toBeTrue();
});
