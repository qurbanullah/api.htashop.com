<?php

namespace Tests\Unit\Feature;

test('the Feature service classes exist', function () {
    expect(class_exists(\App\Services\Feature\FeatureService::class))->toBeTrue();
    expect(class_exists(\App\Services\Features\FeatureModuleAssignmentService::class))->toBeTrue();
    expect(class_exists(\App\Services\Features\FeatureService::class))->toBeTrue();
});
