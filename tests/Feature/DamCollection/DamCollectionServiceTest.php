<?php

namespace Tests\Feature\DamCollection;

test('the DamCollection service classes exist', function () {
    expect(class_exists(\App\Services\Dam\DamCollectionService::class))->toBeTrue();
});
