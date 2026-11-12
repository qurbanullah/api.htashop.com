<?php

namespace Tests\Unit\Tutorial;

use App\Actions\Tutorial\CreateTutorialAction;
use App\Actions\Tutorial\DeleteTutorialAction;
use App\Actions\Tutorial\UpdateTutorialAction;

test('the Tutorial action classes exist', function () {
    expect(class_exists(CreateTutorialAction::class))->toBeTrue();
    expect(class_exists(DeleteTutorialAction::class))->toBeTrue();
    expect(class_exists(UpdateTutorialAction::class))->toBeTrue();
});
