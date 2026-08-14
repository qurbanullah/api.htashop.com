<?php

namespace Tests\Feature\Dam;

test('the Dam action classes exist', function () {
    expect(class_exists(\App\Actions\Dam\CreateDamAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Dam\CreateDamCollectionAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Dam\DeleteDamCollectionAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Dam\ListDamCollectionsAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Dam\ReadDamAssetsAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Dam\ReadDamOwnersAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Dam\SyncDamAssetCollectionsAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Dam\UpdateDamCollectionAction::class))->toBeTrue();
});
