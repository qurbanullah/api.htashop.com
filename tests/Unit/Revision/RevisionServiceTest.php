<?php

namespace Tests\Unit\Revision;

use App\Services\Revision\RevisionService;

test('the Revision service class exists', function () {
    expect(class_exists(RevisionService::class))->toBeTrue();
});
