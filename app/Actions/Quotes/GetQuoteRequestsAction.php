<?php

namespace App\Actions\Quotes;

use App\Models\QuoteRequest;
use Illuminate\Pagination\LengthAwarePaginator;

class GetQuoteRequestsAction
{
    /**
     * Get paginated quote requests with filters.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function execute(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = QuoteRequest::with(['responses', 'latestResponse.sender']);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%");
            });
        }

        if (isset($filters['industry'])) {
            $query->where('industry', $filters['industry']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Order by latest first
        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }
}
