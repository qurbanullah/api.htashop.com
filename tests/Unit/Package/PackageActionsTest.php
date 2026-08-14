<?php

namespace Tests\Unit\Package;

test('the Package action classes exist', function () {
    expect(class_exists(\App\Actions\Packages\PackageCreateAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Packages\PackageDeleteAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Packages\PackagePatchAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Packages\PackageReadAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Packages\PackageSearchByIdAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Packages\PackageShowAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Packages\PackageSortAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Packages\PackageUpdateAction::class))->toBeTrue();
});
