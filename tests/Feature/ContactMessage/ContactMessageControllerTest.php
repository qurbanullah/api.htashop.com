<?php

namespace Tests\Feature\ContactMessage;

test('the ContactMessage controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Contact\ContactMessageAdminController::class))->toBeTrue();
    expect(class_exists(\App\Http\Controllers\V1\Contact\ContactMessageController::class))->toBeTrue();
});
