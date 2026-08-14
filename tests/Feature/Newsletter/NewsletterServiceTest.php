<?php

namespace Tests\Feature\Newsletter;

test('the Newsletter service classes exist', function () {
    expect(class_exists(\App\Services\Newsletter\NewsletterService::class))->toBeTrue();
});
