<?php

namespace Tests\Feature\Tag;

use App\Services\Tag\TagService;

test('the Tag service classes exist', function () {
    expect(class_exists(TagService::class))->toBeTrue();
    expect(class_exists(\App\Services\Tags\TagService::class))->toBeTrue();
});
