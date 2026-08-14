<?php

namespace App\Actions\Feedbacks;

use App\Models\Comment;
use App\Models\Feedback;
use Illuminate\Database\Eloquent\Collection;

/**
 * Single Responsibility: Get comments for a feedback (CRUD only)
 */
class FeedbackGetCommentsAction
{
    /**
     * Get all comments for a feedback
     *
     * @param Feedback $feedback
     * @param bool $includeInternal Include internal comments (default: true)
     * @return Collection
     */
    public function handle(Feedback $feedback, bool $includeInternal = true): Collection
    {
        $query = Comment::where('commentable_type', Feedback::class)
            ->where('commentable_id', $feedback->id)
            ->with('user:id,name,email,avatar')
            ->orderBy('created_at', 'asc');

        if (!$includeInternal) {
            $query->where('is_internal', false);
        }

        return $query->get();
    }
}
