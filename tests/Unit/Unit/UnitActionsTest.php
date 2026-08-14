<?php

namespace Tests\Unit\Unit;

test('the Unit action classes exist', function () {
    expect(class_exists(\App\Actions\Unit\UnitCreateAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Unit\UnitDeleteAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Unit\UnitReadAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Unit\UnitSearchByUuidAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Unit\UnitUpdateAction::class))->toBeTrue();
});
