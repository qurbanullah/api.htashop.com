<?php

namespace Tests\Feature\Eula;

use App\Actions\Eula\ActivateEulaAction;
use App\Actions\Eula\CreateEulaAction;
use App\Actions\Eula\DeleteEulaAction;
use App\Actions\Eula\GetActiveEulaAction;
use App\Actions\Eula\UpdateEulaAction;

test('the Eula action classes exist', function () {
    expect(class_exists(ActivateEulaAction::class))->toBeTrue();
    expect(class_exists(CreateEulaAction::class))->toBeTrue();
    expect(class_exists(DeleteEulaAction::class))->toBeTrue();
    expect(class_exists(GetActiveEulaAction::class))->toBeTrue();
    expect(class_exists(UpdateEulaAction::class))->toBeTrue();
});
