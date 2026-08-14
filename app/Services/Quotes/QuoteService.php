<?php

namespace App\Services\Quotes;

use App\Actions\Quotes\CreateQuoteRequestAction;
use App\Actions\Quotes\SendQuoteResponseAction;
use App\Actions\Quotes\GetQuoteRequestsAction;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class QuoteService
{
    protected CreateQuoteRequestAction $createQuoteRequestAction;
    protected SendQuoteResponseAction $sendQuoteResponseAction;
    protected GetQuoteRequestsAction $getQuoteRequestsAction;

    public function __construct(
        CreateQuoteRequestAction $createQuoteRequestAction,
        SendQuoteResponseAction $sendQuoteResponseAction,
        GetQuoteRequestsAction $getQuoteRequestsAction
    ) {
        $this->createQuoteRequestAction = $createQuoteRequestAction;
        $this->sendQuoteResponseAction = $sendQuoteResponseAction;
        $this->getQuoteRequestsAction = $getQuoteRequestsAction;
    }

    /**
     * Create a new quote request.
     *
     * @param array $data
     * @return QuoteRequest
     */
    public function createQuoteRequest(array $data): QuoteRequest
    {
        return $this->createQuoteRequestAction->execute($data);
    }

    /**
     * Send a quote response to a customer.
     *
     * @param QuoteRequest $quoteRequest
     * @param array $data
     * @param int $sentBy
     * @return QuoteResponse
     */
    public function sendQuoteResponse(QuoteRequest $quoteRequest, array $data, int $sentBy): QuoteResponse
    {
        return $this->sendQuoteResponseAction->execute($quoteRequest, $data, $sentBy);
    }

    /**
     * Get paginated quote requests with filters.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getQuoteRequests(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->getQuoteRequestsAction->execute($filters, $perPage);
    }

    /**
     * Get a single quote request by UUID.
     *
     * @param string $uuid
     * @return QuoteRequest|null
     */
    public function getQuoteRequestByUuid(string $uuid): ?QuoteRequest
    {
        return QuoteRequest::with(['responses.sender', 'latestResponse'])
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Update quote request status.
     *
     * @param QuoteRequest $quoteRequest
     * @param string $status
     * @return bool
     */
    public function updateStatus(QuoteRequest $quoteRequest, string $status): bool
    {
        return $quoteRequest->update(['status' => $status]);
    }

    /**
     * Get quote statistics.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        return [
            'total' => QuoteRequest::count(),
            'pending' => QuoteRequest::pending()->count(),
            'quoted' => QuoteRequest::quoted()->count(),
            'converted' => QuoteRequest::where('status', 'converted')->count(),
            'rejected' => QuoteRequest::where('status', 'rejected')->count(),
            'this_month' => QuoteRequest::whereMonth('created_at', now()->month)->count(),
            'this_week' => QuoteRequest::whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count(),
        ];
    }
}
