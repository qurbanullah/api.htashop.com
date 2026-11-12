<?php

namespace Tests\Unit\Consent;

use App\Services\Eula\ConsentService;

test('the Consent service classes exist', function () {
    expect(class_exists(ConsentService::class))->toBeTrue();
});
