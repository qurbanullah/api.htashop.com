<?php

namespace Tests\Unit\Audit;

use App\Services\Audit\AuditService;

test('the Audit service classes exist', function () {
    expect(class_exists(AuditService::class))->toBeTrue();
});
