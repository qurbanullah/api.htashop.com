<?php

namespace Tests\Unit\Audit;

test('the Audit controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Audit\AuditController::class))->toBeTrue();
});
