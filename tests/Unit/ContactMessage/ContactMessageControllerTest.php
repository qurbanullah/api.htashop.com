<?php

namespace Tests\Unit\ContactMessage;

use App\Http\Controllers\V1\Contact\ContactMessageAdminController;
use App\Http\Controllers\V1\Contact\ContactMessageController;

test('the ContactMessage controller class exists', function () {
    expect(class_exists(ContactMessageAdminController::class))->toBeTrue();
    expect(class_exists(ContactMessageController::class))->toBeTrue();
});
