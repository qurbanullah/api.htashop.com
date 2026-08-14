<?php

namespace Tests\Feature\Label;

test('the Label controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Label\LabelController::class))->toBeTrue();
});
