<?php

namespace App\Actions\ProductReview;

use App\Models\Review;

class ReviewSummaryReadAction
{
    public function handle(string $type, int $id): array
    {
        $row = Review::query()
            ->where('reviewable_type', $type)
            ->where('reviewable_id', $id)
            ->where('status', 'approved')
            ->selectRaw('
                COUNT(*) as total,
                AVG(rating) as average,
                SUM(rating = 5) as five,
                SUM(rating = 4) as four,
                SUM(rating = 3) as three,
                SUM(rating = 2) as two,
                SUM(rating = 1) as one
            ')
            ->first();

        $total = (int) ($row->total ?? 0);

        return [
            'average' => $total > 0 ? round((float) ($row->average ?? 0), 1) : 0,
            'count' => $total,
            'distribution' => [
                5 => (int) ($row->five ?? 0),
                4 => (int) ($row->four ?? 0),
                3 => (int) ($row->three ?? 0),
                2 => (int) ($row->two ?? 0),
                1 => (int) ($row->one ?? 0),
            ],
        ];
    }
}
