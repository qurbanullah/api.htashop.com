<?php

namespace Tests\Feature\Tag;

test('the Tag service classes exist', function () {
    expect(class_exists(\App\Services\Tag\TagService::class))->toBeTrue();
    expect(class_exists(\App\Services\Tags\TagService::class))->toBeTrue();
});
