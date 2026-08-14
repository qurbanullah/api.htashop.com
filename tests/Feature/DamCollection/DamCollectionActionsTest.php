<?php

namespace Tests\Feature\DamCollection;

test('the DamCollection action classes exist', function () {
    expect(class_exists(\App\Actions\Dam\CreateDamCollectionAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Dam\DeleteDamCollectionAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Dam\ListDamCollectionsAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Dam\UpdateDamCollectionAction::class))->toBeTrue();
});
