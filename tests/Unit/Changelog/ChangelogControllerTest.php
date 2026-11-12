<?php

namespace Tests\Unit\Changelog;

use App\Http\Controllers\V1\Changelog\ChangelogController;

test('the Changelog controller class exists', function () {
    expect(class_exists(ChangelogController::class))->toBeTrue();
});
