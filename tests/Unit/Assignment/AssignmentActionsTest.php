<?php

namespace Tests\Unit\Assignment;

test('the Assignment action classes exist', function () {
    expect(class_exists(\App\Actions\Assignment\AssignmentCreateAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Assignment\AssignmentDeleteAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Assignment\AssignmentReadAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Assignment\AssignmentSearchByIdAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Assignment\AssignmentUpdateAction::class))->toBeTrue();
});
