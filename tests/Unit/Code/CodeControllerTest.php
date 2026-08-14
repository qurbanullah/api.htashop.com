<?php

namespace Tests\Unit\Code;

test('the Code controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Code\CodeController::class))->toBeTrue();
});
