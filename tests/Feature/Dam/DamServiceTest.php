<?php

namespace Tests\Feature\Dam;

test('the Dam service classes exist', function () {
    expect(class_exists(\App\Services\Dam\DamCollectionService::class))->toBeTrue();
    expect(class_exists(\App\Services\Dam\DamService::class))->toBeTrue();
});
