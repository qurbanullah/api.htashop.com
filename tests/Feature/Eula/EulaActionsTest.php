<?php

namespace Tests\Feature\Eula;

test('the Eula action classes exist', function () {
    expect(class_exists(\App\Actions\Eula\ActivateEulaAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Eula\CreateEulaAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Eula\DeleteEulaAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Eula\GetActiveEulaAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Eula\UpdateEulaAction::class))->toBeTrue();
});
