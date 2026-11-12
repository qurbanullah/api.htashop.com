<?php

namespace Tests\Feature\Tutorial;

use App\Http\Controllers\V1\Tutorial\AdminTutorialController;
use App\Http\Controllers\V1\Tutorial\PublicTutorialController;

test('the Tutorial controller class exists', function () {
    expect(class_exists(AdminTutorialController::class))->toBeTrue();
    expect(class_exists(PublicTutorialController::class))->toBeTrue();
});
