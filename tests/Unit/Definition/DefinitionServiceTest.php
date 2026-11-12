<?php

namespace Tests\Unit\Definition;

use App\Services\Definition\DefinitionService;

test('the Definition service classes exist', function () {
    expect(class_exists(DefinitionService::class))->toBeTrue();
});
