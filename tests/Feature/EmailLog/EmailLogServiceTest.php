<?php

namespace Tests\Feature\EmailLog;

test('the EmailLog service classes exist', function () {
    expect(class_exists(\App\Services\Email\EmailLogService::class))->toBeTrue();
});
