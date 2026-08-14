<?php

namespace Tests\Feature\Definition;

test('the Definition controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Definition\DefinitionController::class))->toBeTrue();
});
