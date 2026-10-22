<?php

namespace Tests\Feature\PunchoutSession;

use App\Http\Controllers\V1\Punchout\AdminPunchoutSessionController;

test('the PunchoutSession controller class exists', function () {
    expect(class_exists(AdminPunchoutSessionController::class))->toBeTrue();
});
