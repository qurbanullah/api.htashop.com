<?php

namespace Tests\Feature\Value;

use App\Actions\Value\ValueCreateAction;
use App\Actions\Value\ValueDeleteAction;
use App\Actions\Value\ValueReadAction;
use App\Actions\Value\ValueSearchByIdAction;
use App\Actions\Value\ValueUpdateAction;

test('the Value action classes exist', function () {
    expect(class_exists(ValueCreateAction::class))->toBeTrue();
    expect(class_exists(ValueDeleteAction::class))->toBeTrue();
    expect(class_exists(ValueReadAction::class))->toBeTrue();
    expect(class_exists(ValueSearchByIdAction::class))->toBeTrue();
    expect(class_exists(ValueUpdateAction::class))->toBeTrue();
});
