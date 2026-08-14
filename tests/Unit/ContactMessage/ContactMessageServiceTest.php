<?php

namespace Tests\Unit\ContactMessage;

test('the ContactMessage service classes exist', function () {
    expect(class_exists(\App\Services\Messages\ContactMessageService::class))->toBeTrue();
});
