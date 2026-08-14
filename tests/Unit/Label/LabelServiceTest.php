<?php

namespace Tests\Unit\Label;

test('the Label service classes exist', function () {
    expect(class_exists(\App\Services\Label\LabelService::class))->toBeTrue();
});
