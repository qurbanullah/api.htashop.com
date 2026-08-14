<?php

namespace Tests\Feature\Eula;

test('the Eula controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Eula\EulaActionController::class))->toBeTrue();
    expect(class_exists(\App\Http\Controllers\V1\Eula\EulaController::class))->toBeTrue();
});
