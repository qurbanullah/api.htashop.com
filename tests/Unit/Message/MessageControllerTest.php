<?php

namespace Tests\Unit\Message;

use App\Http\Controllers\V1\Contact\ContactMessageAdminController;
use App\Http\Controllers\V1\Contact\ContactMessageController;
use App\Http\Controllers\V1\Message\MessageController;

test('the Message controller class exists', function () {
    expect(class_exists(ContactMessageAdminController::class))->toBeTrue();
    expect(class_exists(ContactMessageController::class))->toBeTrue();
    expect(class_exists(MessageController::class))->toBeTrue();
});
