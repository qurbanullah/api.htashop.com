<?php

namespace App\Actions\Feedbacks;

use App\Models\Comment;
use App\Models\Feedback;

/**
 * Single Responsibility: Add a comment to a feedback (CRUD only)
 */
class FeedbackAddCommentAction
{
    /**
     * Create a comment for a feedback
     *
     * @param Feedback $feedback
     * @param array $data
     * @return Comment
     */
    public function handle(Feedback $feedback, array $data): Comment
    {
        return Comment::create([
            'commentable_type' => Feedback::class,
            'commentable_id' => $feedback->id,
            'user_id' => $data['user_id'],
            'content' => $data['content'],
            'is_internal' => $data['is_internal'] ?? true, // Default to internal (admin only)
            'is_read' => false,
            'metadata' => $data['metadata'] ?? [],
        ]);
    }
}
