<?php

namespace Tests\Unit\Revision;

test('the Revision service class exists', function () {
    expect(class_exists(\App\Services\Revision\RevisionService::class))->toBeTrue();
});
