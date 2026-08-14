<?php

namespace Tests\Unit\PunchoutSession;

test('the PunchoutSession service classes exist', function () {
    expect(class_exists(\App\Services\Punchout\PunchoutSessionService::class))->toBeTrue();
});
