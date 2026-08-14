<?php

namespace Tests\Unit\Audit;

test('the Audit service classes exist', function () {
    expect(class_exists(\App\Services\Audit\AuditService::class))->toBeTrue();
});
