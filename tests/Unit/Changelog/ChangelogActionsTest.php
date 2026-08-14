<?php

namespace Tests\Unit\Changelog;

test('the Changelog action classes exist', function () {
    expect(class_exists(\App\Actions\Changelogs\ChangelogCreateAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Changelogs\ChangelogUpdateAction::class))->toBeTrue();
});
