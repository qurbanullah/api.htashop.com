<?php

namespace Tests\Feature\PunchoutSession;

use App\Services\Punchout\PunchoutSessionService;

test('the PunchoutSession service classes exist', function () {
    expect(class_exists(PunchoutSessionService::class))->toBeTrue();
});
