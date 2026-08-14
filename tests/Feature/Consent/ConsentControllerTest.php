<?php

namespace Tests\Feature\Consent;

test('the Consent controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Eula\ConsentController::class))->toBeTrue();
});
