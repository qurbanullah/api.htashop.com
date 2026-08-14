<?php

namespace Tests\Feature\Message;

test('the Message service classes exist', function () {
    expect(class_exists(\App\Services\Messages\ContactMessageService::class))->toBeTrue();
});
