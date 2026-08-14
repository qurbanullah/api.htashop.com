<?php

namespace Tests\Unit\EmailLog;

test('the EmailLog service classes exist', function () {
    expect(class_exists(\App\Services\Email\EmailLogService::class))->toBeTrue();
});
