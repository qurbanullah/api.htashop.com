<?php

namespace App\Actions\Review;

use App\Models\Review;
use App\Models\User;

class DeleteReviewAction
{
    /**
     * Delete a review
     *
     * @param Review $review
     * @param User $user
     * @return bool
     * @throws \Exception
     */
    public function execute(Review $review, User $user): bool
    {
        // Ensure user owns this review or is admin
        if ($review->user_id !== $user->id && !$user->isAdmin()) {
            throw new \Exception('You can only delete your own reviews.');
        }

        // Delete the review (cascade will handle reviewables)
        return $review->delete();
    }
}
