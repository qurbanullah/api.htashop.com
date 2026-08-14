<?php

namespace Tests\Feature\Label;

test('the Label action classes exist', function () {
    expect(class_exists(\App\Actions\Label\LabelCreateAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Label\LabelDeleteAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Label\LabelReadAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Label\LabelSearchByUuidAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Label\LabelUpdateAction::class))->toBeTrue();
});
