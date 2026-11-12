<?php

namespace Tests\Feature\Feature;

use App\Services\Feature\FeatureService;
use App\Services\Features\FeatureModuleAssignmentService;

test('the Feature service classes exist', function () {
    expect(class_exists(FeatureService::class))->toBeTrue();
    expect(class_exists(FeatureModuleAssignmentService::class))->toBeTrue();
    expect(class_exists(\App\Services\Features\FeatureService::class))->toBeTrue();
});
