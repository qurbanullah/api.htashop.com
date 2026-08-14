<?php

namespace Tests\Feature\Code;

test('the Code action classes exist', function () {
    expect(class_exists(\App\Actions\Code\CodeCreateAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Code\CodeDeleteAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Code\CodeReadAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Code\CodeSearchByIdAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Code\CodeUpdateAction::class))->toBeTrue();
});
