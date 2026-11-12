<?php

namespace Tests\Feature\Value;

use App\Services\Value\ValueService;

test('the Value service classes exist', function () {
    expect(class_exists(ValueService::class))->toBeTrue();
});
