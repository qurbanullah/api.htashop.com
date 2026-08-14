<?php

namespace App\Actions\Review;

use App\Models\Review;
use App\Models\User;

class ToggleHelpfulVoteAction
{
    /**
     * Toggle helpful vote for a review
     *
     * @param Review $review
     * @param User $user
     * @return array ['voted' => bool, 'helpful_count' => int]
     * @throws \Exception
     */
    public function execute(Review $review, User $user): array
    {
        // User cannot vote for their own review
        if ($review->user_id === $user->id) {
            throw new \Exception('You cannot vote for your own review.');
        }

        // Toggle the vote
        $voted = $review->toggleHelpful($user);

        return [
            'voted' => $voted,
            'helpful_count' => $review->helpful_count,
        ];
    }
}
