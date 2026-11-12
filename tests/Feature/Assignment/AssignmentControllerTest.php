<?php

namespace Tests\Feature\Assignment;

use App\Http\Controllers\V1\Assignment\AssignmentController;

test('the Assignment controller class exists', function () {
    expect(class_exists(AssignmentController::class))->toBeTrue();
});
