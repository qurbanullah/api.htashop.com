<?php

namespace Tests\Unit\Code;

test('the Code service classes exist', function () {
    expect(class_exists(\App\Services\Code\CodeService::class))->toBeTrue();
});
