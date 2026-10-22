<?php

namespace Tests\Unit\PunchoutSession;

use App\Actions\Punchout\BuildPunchoutSessionHandoffUrlAction;
use App\Actions\Punchout\BuildPunchoutSessionStartUrlAction;
use App\Actions\Punchout\CleanupPunchoutSessionsAction;
use App\Actions\Punchout\CompletePunchoutSessionAction;
use App\Actions\Punchout\CreatePunchoutSessionAction;
use App\Actions\Punchout\ExpirePunchoutSessionAction;
use App\Actions\Punchout\PunchoutSessionReadAction;
use App\Actions\Punchout\PunchoutSessionSearchByUuidAction;
use App\Actions\Punchout\ResolvePunchoutSessionAction;

test('the PunchoutSession action classes exist', function () {
    expect(class_exists(BuildPunchoutSessionHandoffUrlAction::class))->toBeTrue();
    expect(class_exists(BuildPunchoutSessionStartUrlAction::class))->toBeTrue();
    expect(class_exists(CleanupPunchoutSessionsAction::class))->toBeTrue();
    expect(class_exists(CompletePunchoutSessionAction::class))->toBeTrue();
    expect(class_exists(CreatePunchoutSessionAction::class))->toBeTrue();
    expect(class_exists(ExpirePunchoutSessionAction::class))->toBeTrue();
    expect(class_exists(PunchoutSessionReadAction::class))->toBeTrue();
    expect(class_exists(PunchoutSessionSearchByUuidAction::class))->toBeTrue();
    expect(class_exists(ResolvePunchoutSessionAction::class))->toBeTrue();
});
