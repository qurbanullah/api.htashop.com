<?php

namespace Tests\Feature\Tag;

use App\Http\Controllers\V1\Tag\TagController;

test('the Tag controller class exists', function () {
    expect(class_exists(TagController::class))->toBeTrue();
});
