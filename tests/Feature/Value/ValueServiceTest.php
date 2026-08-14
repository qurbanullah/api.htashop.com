<?php

namespace Tests\Feature\Value;

test('the Value service classes exist', function () {
    expect(class_exists(\App\Services\Value\ValueService::class))->toBeTrue();
});
