<?php

namespace Tests\Unit\Definition;

test('the Definition action classes exist', function () {
    expect(class_exists(\App\Actions\Definition\DefinitionCreateAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Definition\DefinitionDeleteAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Definition\DefinitionReadAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Definition\DefinitionSearchByUuidAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Definition\DefinitionUpdateAction::class))->toBeTrue();
});
