<?php

namespace Tests\Feature\Unit;

use App\Actions\Unit\UnitCreateAction;
use App\Actions\Unit\UnitDeleteAction;
use App\Actions\Unit\UnitReadAction;
use App\Actions\Unit\UnitSearchByUuidAction;
use App\Actions\Unit\UnitUpdateAction;

test('the Unit action classes exist', function () {
    expect(class_exists(UnitCreateAction::class))->toBeTrue();
    expect(class_exists(UnitDeleteAction::class))->toBeTrue();
    expect(class_exists(UnitReadAction::class))->toBeTrue();
    expect(class_exists(UnitSearchByUuidAction::class))->toBeTrue();
    expect(class_exists(UnitUpdateAction::class))->toBeTrue();
});
