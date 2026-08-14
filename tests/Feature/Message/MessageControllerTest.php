<?php

namespace Tests\Feature\Message;

test('the Message controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Contact\ContactMessageAdminController::class))->toBeTrue();
    expect(class_exists(\App\Http\Controllers\V1\Contact\ContactMessageController::class))->toBeTrue();
    expect(class_exists(\App\Http\Controllers\V1\Message\MessageController::class))->toBeTrue();
});
