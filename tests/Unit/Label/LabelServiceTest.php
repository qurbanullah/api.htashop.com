<?php

namespace Tests\Unit\Label;

use App\Services\Label\LabelService;

test('the Label service classes exist', function () {
    expect(class_exists(LabelService::class))->toBeTrue();
});
