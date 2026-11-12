<?php

namespace Tests\Feature\DamCollection;

use App\Actions\Dam\CreateDamCollectionAction;
use App\Actions\Dam\DeleteDamCollectionAction;
use App\Actions\Dam\ListDamCollectionsAction;
use App\Actions\Dam\UpdateDamCollectionAction;

test('the DamCollection action classes exist', function () {
    expect(class_exists(CreateDamCollectionAction::class))->toBeTrue();
    expect(class_exists(DeleteDamCollectionAction::class))->toBeTrue();
    expect(class_exists(ListDamCollectionsAction::class))->toBeTrue();
    expect(class_exists(UpdateDamCollectionAction::class))->toBeTrue();
});
