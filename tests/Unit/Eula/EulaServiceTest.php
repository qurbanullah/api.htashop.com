<?php

namespace Tests\Unit\Eula;

use App\Services\Eula\EulaService;

test('the Eula service classes exist', function () {
    expect(class_exists(EulaService::class))->toBeTrue();
});
