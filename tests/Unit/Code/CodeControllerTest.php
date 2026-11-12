<?php

namespace Tests\Unit\Code;

use App\Http\Controllers\V1\Code\CodeController;

test('the Code controller class exists', function () {
    expect(class_exists(CodeController::class))->toBeTrue();
});
