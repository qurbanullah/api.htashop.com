<?php

namespace Tests\Unit\Comment;

test('the Comment action classes exist', function () {
    expect(class_exists(\App\Actions\Feedbacks\FeedbackAddCommentAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Feedbacks\FeedbackGetCommentsAction::class))->toBeTrue();
});
