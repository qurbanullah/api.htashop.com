<?php

namespace App\Actions\Review;

use Illuminate\Database\Eloquent\Model;

class GetReviewStatisticsAction
{
    /**
     * Get review statistics for a reviewable model
     *
     * @param Model $reviewable The model being reviewed (Product, Vendor, etc.)
     * @return array
     */
    public function execute(Model $reviewable): array
    {
        return $reviewable->reviewStatistics();
    }
}
