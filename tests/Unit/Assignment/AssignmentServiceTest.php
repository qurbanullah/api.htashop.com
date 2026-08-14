<?php

namespace Tests\Unit\Assignment;

test('the Assignment service classes exist', function () {
    expect(class_exists(\App\Services\Assignment\AssignmentService::class))->toBeTrue();
    expect(class_exists(\App\Services\Features\FeatureModuleAssignmentService::class))->toBeTrue();
});
