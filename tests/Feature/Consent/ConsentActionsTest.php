<?php

namespace Tests\Feature\Consent;

use App\Actions\Eula\CheckConsentAction;
use App\Actions\Eula\RecordConsentAction;

test('the Consent action classes exist', function () {
    expect(class_exists(CheckConsentAction::class))->toBeTrue();
    expect(class_exists(RecordConsentAction::class))->toBeTrue();
});
