<?php

namespace Tests\Feature\Label;

use App\Actions\Label\LabelCreateAction;
use App\Actions\Label\LabelDeleteAction;
use App\Actions\Label\LabelReadAction;
use App\Actions\Label\LabelSearchByUuidAction;
use App\Actions\Label\LabelUpdateAction;

test('the Label action classes exist', function () {
    expect(class_exists(LabelCreateAction::class))->toBeTrue();
    expect(class_exists(LabelDeleteAction::class))->toBeTrue();
    expect(class_exists(LabelReadAction::class))->toBeTrue();
    expect(class_exists(LabelSearchByUuidAction::class))->toBeTrue();
    expect(class_exists(LabelUpdateAction::class))->toBeTrue();
});
