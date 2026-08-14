<?php

namespace Tests\Unit\Message;

test('the Message service classes exist', function () {
    expect(class_exists(\App\Services\Messages\ContactMessageService::class))->toBeTrue();
});
