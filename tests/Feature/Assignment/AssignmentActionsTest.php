<?php

namespace Tests\Feature\Assignment;

use App\Actions\Assignment\AssignmentCreateAction;
use App\Actions\Assignment\AssignmentDeleteAction;
use App\Actions\Assignment\AssignmentReadAction;
use App\Actions\Assignment\AssignmentSearchByIdAction;
use App\Actions\Assignment\AssignmentUpdateAction;

test('the Assignment action classes exist', function () {
    expect(class_exists(AssignmentCreateAction::class))->toBeTrue();
    expect(class_exists(AssignmentDeleteAction::class))->toBeTrue();
    expect(class_exists(AssignmentReadAction::class))->toBeTrue();
    expect(class_exists(AssignmentSearchByIdAction::class))->toBeTrue();
    expect(class_exists(AssignmentUpdateAction::class))->toBeTrue();
});
