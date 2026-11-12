<?php

namespace Tests\Feature\ContactMessage;

use App\Services\Messages\ContactMessageService;

test('the ContactMessage service classes exist', function () {
    expect(class_exists(ContactMessageService::class))->toBeTrue();
});
