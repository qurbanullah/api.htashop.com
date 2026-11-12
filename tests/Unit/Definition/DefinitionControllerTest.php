<?php

namespace Tests\Unit\Definition;

use App\Http\Controllers\V1\Definition\DefinitionController;

test('the Definition controller class exists', function () {
    expect(class_exists(DefinitionController::class))->toBeTrue();
});
