<?php

namespace Tests\Feature\Definition;

test('the Definition service classes exist', function () {
    expect(class_exists(\App\Services\Definition\DefinitionService::class))->toBeTrue();
});
