<?php

namespace App\Actions\Review;

use App\Models\Review;
use App\Models\User;

class UpdateReviewAction
{
    /**
     * Update an existing review
     *
     * @param Review $review
     * @param User $user
     * @param array $data
     * @return Review
     * @throws \Exception
     */
    public function execute(Review $review, User $user, array $data): Review
    {
        // Ensure user owns this review
        if ($review->user_id !== $user->id) {
            throw new \Exception('You can only update your own reviews.');
        }

        // Update the review using data_get() for safe array access
        $review->update([
            'rating' => data_get($data, 'rating', $review->rating),
            'title' => data_get($data, 'title', $review->title),
            'comment' => data_get($data, 'comment', $review->comment),
        ]);

        // Reload relationships
        $review->load(['user', 'reviewables']);

        return $review;
    }
}
