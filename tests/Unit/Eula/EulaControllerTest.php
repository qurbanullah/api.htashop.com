<?php

namespace Tests\Unit\Eula;

use App\Http\Controllers\V1\Eula\EulaActionController;
use App\Http\Controllers\V1\Eula\EulaController;

test('the Eula controller class exists', function () {
    expect(class_exists(EulaActionController::class))->toBeTrue();
    expect(class_exists(EulaController::class))->toBeTrue();
});
