<?php

namespace Tests\Unit\Message;

use App\Services\Messages\ContactMessageService;

test('the Message service classes exist', function () {
    expect(class_exists(ContactMessageService::class))->toBeTrue();
});
