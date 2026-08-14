<?php

namespace Tests\Feature\Tutorial;

test('the Tutorial controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Tutorial\AdminTutorialController::class))->toBeTrue();
    expect(class_exists(\App\Http\Controllers\V1\Tutorial\PublicTutorialController::class))->toBeTrue();
});
