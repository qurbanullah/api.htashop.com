<?php

namespace Tests\Feature\Tag;

test('the Tag controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Tag\TagController::class))->toBeTrue();
});
