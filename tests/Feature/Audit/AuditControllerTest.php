<?php

namespace Tests\Feature\Audit;

use App\Http\Controllers\V1\Audit\AuditController;

test('the Audit controller class exists', function () {
    expect(class_exists(AuditController::class))->toBeTrue();
});
