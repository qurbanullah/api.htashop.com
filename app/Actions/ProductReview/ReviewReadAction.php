<?php

namespace App\Actions\ProductReview;

use App\Models\Review;
use Illuminate\Pagination\LengthAwarePaginator;

class ReviewReadAction
{
    public function handle(string $type, int $id, int $perPage = 10): LengthAwarePaginator
    {
        return Review::query()
            ->where('reviewable_type', $type)
            ->where('reviewable_id', $id)
            ->where('status', 'approved')
            ->with(['user' => fn ($query) => $query->select('id', 'name', 'email')])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
