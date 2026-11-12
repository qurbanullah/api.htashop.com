<?php

namespace Tests\Feature\Feedback;

use App\Actions\Feedbacks\FeedbackAddCommentAction;
use App\Actions\Feedbacks\FeedbackApprovedFeaturesAction;
use App\Actions\Feedbacks\FeedbackCreateAction;
use App\Actions\Feedbacks\FeedbackDeleteAction;
use App\Actions\Feedbacks\FeedbackGetCommentsAction;
use App\Actions\Feedbacks\FeedbackSearchByUuidAction;
use App\Actions\Feedbacks\FeedbackShowAction;
use App\Actions\Feedbacks\FeedbackUpdateAction;

test('the Feedback action classes exist', function () {
    expect(class_exists(FeedbackAddCommentAction::class))->toBeTrue();
    expect(class_exists(FeedbackApprovedFeaturesAction::class))->toBeTrue();
    expect(class_exists(FeedbackCreateAction::class))->toBeTrue();
    expect(class_exists(FeedbackDeleteAction::class))->toBeTrue();
    expect(class_exists(FeedbackGetCommentsAction::class))->toBeTrue();
    expect(class_exists(FeedbackSearchByUuidAction::class))->toBeTrue();
    expect(class_exists(FeedbackShowAction::class))->toBeTrue();
    expect(class_exists(FeedbackUpdateAction::class))->toBeTrue();
});
