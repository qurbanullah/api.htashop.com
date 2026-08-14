<?php

namespace Tests\Feature\Consent;

test('the Consent action classes exist', function () {
    expect(class_exists(\App\Actions\Eula\CheckConsentAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Eula\RecordConsentAction::class))->toBeTrue();
});
