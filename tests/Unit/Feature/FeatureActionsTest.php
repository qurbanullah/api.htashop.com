<?php

namespace Tests\Unit\Feature;

use App\Actions\Features\AttachFeaturesAction;
use App\Actions\Features\CreateFeatureAction;
use App\Actions\Features\DetachFeaturesAction;
use App\Actions\Features\FeatureCreateAction;
use App\Actions\Features\FeatureDeleteAction;
use App\Actions\Features\FeaturePatchAction;
use App\Actions\Features\FeatureReadAction;
use App\Actions\Features\FeatureSearchByIdAction;
use App\Actions\Features\FeatureShowAction;
use App\Actions\Features\FeatureSortAction;
use App\Actions\Features\FeatureUpdateAction;
use App\Actions\Features\GetFeaturesAction;
use App\Actions\Features\GetPopularFeaturesAction;
use App\Actions\Features\SyncFeaturesAction;
use App\Actions\Feedbacks\FeedbackApprovedFeaturesAction;

test('the Feature action classes exist', function () {
    expect(class_exists(AttachFeaturesAction::class))->toBeTrue();
    expect(class_exists(CreateFeatureAction::class))->toBeTrue();
    expect(class_exists(DetachFeaturesAction::class))->toBeTrue();
    expect(class_exists(FeatureCreateAction::class))->toBeTrue();
    expect(class_exists(FeatureDeleteAction::class))->toBeTrue();
    expect(class_exists(FeaturePatchAction::class))->toBeTrue();
    expect(class_exists(FeatureReadAction::class))->toBeTrue();
    expect(class_exists(FeatureSearchByIdAction::class))->toBeTrue();
    expect(class_exists(FeatureShowAction::class))->toBeTrue();
    expect(class_exists(FeatureSortAction::class))->toBeTrue();
    expect(class_exists(FeatureUpdateAction::class))->toBeTrue();
    expect(class_exists(GetFeaturesAction::class))->toBeTrue();
    expect(class_exists(GetPopularFeaturesAction::class))->toBeTrue();
    expect(class_exists(SyncFeaturesAction::class))->toBeTrue();
    expect(class_exists(FeedbackApprovedFeaturesAction::class))->toBeTrue();
});
