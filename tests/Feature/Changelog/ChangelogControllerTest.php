<?php

namespace Tests\Feature\Changelog;

test('the Changelog controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Changelog\ChangelogController::class))->toBeTrue();
});
