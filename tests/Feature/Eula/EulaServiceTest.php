<?php

namespace Tests\Feature\Eula;

test('the Eula service classes exist', function () {
    expect(class_exists(\App\Services\Eula\EulaService::class))->toBeTrue();
});
