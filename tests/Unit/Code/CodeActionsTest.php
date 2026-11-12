<?php

namespace Tests\Unit\Code;

use App\Actions\Code\CodeCreateAction;
use App\Actions\Code\CodeDeleteAction;
use App\Actions\Code\CodeReadAction;
use App\Actions\Code\CodeSearchByIdAction;
use App\Actions\Code\CodeUpdateAction;

test('the Code action classes exist', function () {
    expect(class_exists(CodeCreateAction::class))->toBeTrue();
    expect(class_exists(CodeDeleteAction::class))->toBeTrue();
    expect(class_exists(CodeReadAction::class))->toBeTrue();
    expect(class_exists(CodeSearchByIdAction::class))->toBeTrue();
    expect(class_exists(CodeUpdateAction::class))->toBeTrue();
});
