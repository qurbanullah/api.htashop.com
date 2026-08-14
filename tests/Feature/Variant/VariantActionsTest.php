<?php

namespace Tests\Feature\Variant;

test('the Variant action classes exist', function () {
    expect(class_exists(\App\Actions\Variant\VariantCreateAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Variant\VariantDeleteAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Variant\VariantReadAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Variant\VariantSearchByUuidAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Variant\VariantUpdateAction::class))->toBeTrue();
});
