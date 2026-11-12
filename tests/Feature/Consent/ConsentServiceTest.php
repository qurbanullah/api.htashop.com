<?php

namespace Tests\Feature\Consent;

use App\Services\Eula\ConsentService;

test('the Consent service classes exist', function () {
    expect(class_exists(ConsentService::class))->toBeTrue();
});
