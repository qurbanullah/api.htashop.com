<?php

namespace Tests\Unit\Package;

use App\Actions\Packages\PackageCreateAction;
use App\Actions\Packages\PackageDeleteAction;
use App\Actions\Packages\PackagePatchAction;
use App\Actions\Packages\PackageReadAction;
use App\Actions\Packages\PackageSearchByIdAction;
use App\Actions\Packages\PackageShowAction;
use App\Actions\Packages\PackageSortAction;
use App\Actions\Packages\PackageUpdateAction;

test('the Package action classes exist', function () {
    expect(class_exists(PackageCreateAction::class))->toBeTrue();
    expect(class_exists(PackageDeleteAction::class))->toBeTrue();
    expect(class_exists(PackagePatchAction::class))->toBeTrue();
    expect(class_exists(PackageReadAction::class))->toBeTrue();
    expect(class_exists(PackageSearchByIdAction::class))->toBeTrue();
    expect(class_exists(PackageShowAction::class))->toBeTrue();
    expect(class_exists(PackageSortAction::class))->toBeTrue();
    expect(class_exists(PackageUpdateAction::class))->toBeTrue();
});
