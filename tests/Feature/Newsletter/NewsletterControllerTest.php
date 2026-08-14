<?php

namespace Tests\Feature\Newsletter;

test('the Newsletter controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Newsletter\AdminNewsletterController::class))->toBeTrue();
    expect(class_exists(\App\Http\Controllers\V1\Newsletter\NewsletterApiController::class))->toBeTrue();
});
