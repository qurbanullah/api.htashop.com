<?php

namespace App\Actions\Review;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class GetReviewsAction
{
    /**
     * Get paginated reviews for a reviewable model
     *
     * @param Model $reviewable The model being reviewed (Product, Vendor, etc.)
     * @param int $perPage
     * @param int|null $rating Filter by rating
     * @return LengthAwarePaginator
     */
    public function execute(Model $reviewable, int $perPage = 15, ?int $rating = null): LengthAwarePaginator
    {
        $query = $reviewable->approvedReviews()
            ->with(['user:id,name,email']);

        // Filter by rating if specified
        if ($rating !== null) {
            $query->where('rating', $rating);
        }

        return $query->paginate($perPage);
    }
}
