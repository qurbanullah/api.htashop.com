<?php

namespace Tests\Feature\DamCollection;

use App\Http\Controllers\V1\Dam\DamCollectionController;

test('the DamCollection controller class exists', function () {
    expect(class_exists(DamCollectionController::class))->toBeTrue();
});
