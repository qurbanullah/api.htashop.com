<?php

namespace Tests\Feature\Code;

use App\Services\Code\CodeService;

test('the Code service classes exist', function () {
    expect(class_exists(CodeService::class))->toBeTrue();
});
