<?php

namespace Tests\Feature\Feedback;

test('the Feedback action classes exist', function () {
    expect(class_exists(\App\Actions\Feedbacks\FeedbackAddCommentAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Feedbacks\FeedbackApprovedFeaturesAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Feedbacks\FeedbackCreateAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Feedbacks\FeedbackDeleteAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Feedbacks\FeedbackGetCommentsAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Feedbacks\FeedbackSearchByUuidAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Feedbacks\FeedbackShowAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Feedbacks\FeedbackUpdateAction::class))->toBeTrue();
});
