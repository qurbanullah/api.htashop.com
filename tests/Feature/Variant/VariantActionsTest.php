<?php

namespace Tests\Feature\Variant;

use App\Actions\Variant\VariantCreateAction;
use App\Actions\Variant\VariantDeleteAction;
use App\Actions\Variant\VariantReadAction;
use App\Actions\Variant\VariantSearchByUuidAction;
use App\Actions\Variant\VariantUpdateAction;

test('the Variant action classes exist', function () {
    expect(class_exists(VariantCreateAction::class))->toBeTrue();
    expect(class_exists(VariantDeleteAction::class))->toBeTrue();
    expect(class_exists(VariantReadAction::class))->toBeTrue();
    expect(class_exists(VariantSearchByUuidAction::class))->toBeTrue();
    expect(class_exists(VariantUpdateAction::class))->toBeTrue();
});
