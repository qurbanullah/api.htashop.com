<?php

namespace Tests\Unit\Definition;

use App\Actions\Definition\DefinitionCreateAction;
use App\Actions\Definition\DefinitionDeleteAction;
use App\Actions\Definition\DefinitionReadAction;
use App\Actions\Definition\DefinitionSearchByUuidAction;
use App\Actions\Definition\DefinitionUpdateAction;

test('the Definition action classes exist', function () {
    expect(class_exists(DefinitionCreateAction::class))->toBeTrue();
    expect(class_exists(DefinitionDeleteAction::class))->toBeTrue();
    expect(class_exists(DefinitionReadAction::class))->toBeTrue();
    expect(class_exists(DefinitionSearchByUuidAction::class))->toBeTrue();
    expect(class_exists(DefinitionUpdateAction::class))->toBeTrue();
});
