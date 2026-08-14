<?php

namespace Tests\Unit\Feature;

test('the Feature action classes exist', function () {
    expect(class_exists(\App\Actions\Features\AttachFeaturesAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Features\CreateFeatureAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Features\DetachFeaturesAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Features\FeatureCreateAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Features\FeatureDeleteAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Features\FeaturePatchAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Features\FeatureReadAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Features\FeatureSearchByIdAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Features\FeatureShowAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Features\FeatureSortAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Features\FeatureUpdateAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Features\GetFeaturesAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Features\GetPopularFeaturesAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Features\SyncFeaturesAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Feedbacks\FeedbackApprovedFeaturesAction::class))->toBeTrue();
});
