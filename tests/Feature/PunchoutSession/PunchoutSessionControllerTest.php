<?php

namespace Tests\Feature\PunchoutSession;

test('the PunchoutSession controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Punchout\AdminPunchoutSessionController::class))->toBeTrue();
});
