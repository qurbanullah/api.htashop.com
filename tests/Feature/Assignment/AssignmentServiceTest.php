<?php

namespace Tests\Feature\Assignment;

use App\Services\Assignment\AssignmentService;
use App\Services\Features\FeatureModuleAssignmentService;

test('the Assignment service classes exist', function () {
    expect(class_exists(AssignmentService::class))->toBeTrue();
    expect(class_exists(FeatureModuleAssignmentService::class))->toBeTrue();
});
