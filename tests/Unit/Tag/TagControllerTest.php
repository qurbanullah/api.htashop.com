<?php

namespace Tests\Unit\Tag;

use App\Http\Controllers\V1\Tag\TagController;

test('the Tag controller class exists', function () {
    expect(class_exists(TagController::class))->toBeTrue();
});
