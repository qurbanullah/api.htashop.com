<?php

namespace Tests\Feature\Tutorial;

test('the Tutorial service classes exist', function () {
    expect(class_exists(\App\Services\Tutorial\TutorialService::class))->toBeTrue();
});
