<?php

namespace Tests\Unit\Tutorial;

use App\Services\Tutorial\TutorialService;

test('the Tutorial service classes exist', function () {
    expect(class_exists(TutorialService::class))->toBeTrue();
});
