<?php

namespace Tests\Unit\Consent;

use App\Http\Controllers\V1\Eula\ConsentController;

test('the Consent controller class exists', function () {
    expect(class_exists(ConsentController::class))->toBeTrue();
});
