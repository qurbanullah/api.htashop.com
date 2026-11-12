<?php

namespace Tests\Unit\Changelog;

use App\Actions\Changelogs\ChangelogCreateAction;
use App\Actions\Changelogs\ChangelogUpdateAction;

test('the Changelog action classes exist', function () {
    expect(class_exists(ChangelogCreateAction::class))->toBeTrue();
    expect(class_exists(ChangelogUpdateAction::class))->toBeTrue();
});
