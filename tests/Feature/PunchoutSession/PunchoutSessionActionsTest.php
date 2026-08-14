<?php

namespace Tests\Feature\PunchoutSession;

test('the PunchoutSession action classes exist', function () {
    expect(class_exists(\App\Actions\Punchout\BuildPunchoutSessionHandoffUrlAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Punchout\BuildPunchoutSessionStartUrlAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Punchout\CleanupPunchoutSessionsAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Punchout\CompletePunchoutSessionAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Punchout\CreatePunchoutSessionAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Punchout\ExpirePunchoutSessionAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Punchout\PunchoutSessionReadAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Punchout\PunchoutSessionSearchByUuidAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Punchout\ResolvePunchoutSessionAction::class))->toBeTrue();
});
