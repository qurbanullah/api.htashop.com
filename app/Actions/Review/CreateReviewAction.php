<?php

namespace App\Actions\Review;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CreateReviewAction
{
    /**
     * Create a new review for a reviewable model
     *
     * @param Model $reviewable The model being reviewed (Product, Vendor, etc.)
     * @param User $user The user creating the review
     * @param array $data Review data (rating, title, comment, order_id)
     * @return Review
     */
    public function execute(Model $reviewable, User $user, array $data): Review
    {
        // Check if user already reviewed this item
        if ($reviewable->hasReviewByUser($user->id)) {
            throw new \Exception('You have already reviewed this item.');
        }

        // Create the review using data_get() for safe array access
        $review = Review::create([
            'user_id' => $user->id,
            'order_id' => data_get($data, 'order_id'),
            'rating' => data_get($data, 'rating'),
            'title' => data_get($data, 'title'),
            'comment' => data_get($data, 'comment'),
            'is_approved' => data_get($data, 'is_approved', true),
            'helpful_count' => 0,
        ]);

        // Attach review to the reviewable model (polymorphic relation)
        $reviewable->reviews()->attach($review->id, [
            'type' => 'subject',
        ]);

        // Load relationships for response
        $review->load(['user', 'reviewables']);

        return $review;
    }
}
