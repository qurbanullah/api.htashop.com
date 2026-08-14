<?php

namespace Tests\Unit\Assignment;

test('the Assignment controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Assignment\AssignmentController::class))->toBeTrue();
});
