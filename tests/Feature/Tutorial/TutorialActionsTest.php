<?php

namespace Tests\Feature\Tutorial;

test('the Tutorial action classes exist', function () {
    expect(class_exists(\App\Actions\Tutorial\CreateTutorialAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tutorial\DeleteTutorialAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tutorial\UpdateTutorialAction::class))->toBeTrue();
});
