<?php

namespace Tests\Unit\Value;

test('the Value action classes exist', function () {
    expect(class_exists(\App\Actions\Value\ValueCreateAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Value\ValueDeleteAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Value\ValueReadAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Value\ValueSearchByIdAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Value\ValueUpdateAction::class))->toBeTrue();
});
