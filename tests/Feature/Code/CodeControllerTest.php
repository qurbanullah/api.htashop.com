<?php

namespace Tests\Feature\Code;

use App\Http\Controllers\V1\Code\CodeController;

test('the Code controller class exists', function () {
    expect(class_exists(CodeController::class))->toBeTrue();
});
