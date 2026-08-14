<?php

namespace Tests\Feature\Consent;

test('the Consent service classes exist', function () {
    expect(class_exists(\App\Services\Eula\ConsentService::class))->toBeTrue();
});
