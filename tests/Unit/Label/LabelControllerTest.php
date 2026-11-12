<?php

namespace Tests\Unit\Label;

use App\Http\Controllers\V1\Label\LabelController;

test('the Label controller class exists', function () {
    expect(class_exists(LabelController::class))->toBeTrue();
});
