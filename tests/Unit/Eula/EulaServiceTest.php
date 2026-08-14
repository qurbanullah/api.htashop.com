<?php

namespace Tests\Unit\Eula;

test('the Eula service classes exist', function () {
    expect(class_exists(\App\Services\Eula\EulaService::class))->toBeTrue();
});
