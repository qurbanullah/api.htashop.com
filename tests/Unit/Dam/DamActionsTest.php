<?php

namespace Tests\Unit\Dam;

use App\Actions\Dam\CreateDamAction;
use App\Actions\Dam\CreateDamCollectionAction;
use App\Actions\Dam\DeleteDamCollectionAction;
use App\Actions\Dam\ListDamCollectionsAction;
use App\Actions\Dam\ReadDamAssetsAction;
use App\Actions\Dam\ReadDamOwnersAction;
use App\Actions\Dam\SyncDamAssetCollectionsAction;
use App\Actions\Dam\UpdateDamCollectionAction;

test('the Dam action classes exist', function () {
    expect(class_exists(CreateDamAction::class))->toBeTrue();
    expect(class_exists(CreateDamCollectionAction::class))->toBeTrue();
    expect(class_exists(DeleteDamCollectionAction::class))->toBeTrue();
    expect(class_exists(ListDamCollectionsAction::class))->toBeTrue();
    expect(class_exists(ReadDamAssetsAction::class))->toBeTrue();
    expect(class_exists(ReadDamOwnersAction::class))->toBeTrue();
    expect(class_exists(SyncDamAssetCollectionsAction::class))->toBeTrue();
    expect(class_exists(UpdateDamCollectionAction::class))->toBeTrue();
});
