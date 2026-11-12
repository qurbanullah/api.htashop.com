<?php

namespace Tests\Feature\Comment;

use App\Actions\Feedbacks\FeedbackAddCommentAction;
use App\Actions\Feedbacks\FeedbackGetCommentsAction;

test('the Comment action classes exist', function () {
    expect(class_exists(FeedbackAddCommentAction::class))->toBeTrue();
    expect(class_exists(FeedbackGetCommentsAction::class))->toBeTrue();
});
