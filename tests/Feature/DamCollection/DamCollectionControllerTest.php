<?php

namespace Tests\Feature\DamCollection;

test('the DamCollection controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Dam\DamCollectionController::class))->toBeTrue();
});
