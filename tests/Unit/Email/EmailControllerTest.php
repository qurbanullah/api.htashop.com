<?php

namespace Tests\Unit\Email;

test('the Email controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Email\EmailController::class))->toBeTrue();
    expect(class_exists(\App\Http\Controllers\V1\Email\EmailLogController::class))->toBeTrue();
});
