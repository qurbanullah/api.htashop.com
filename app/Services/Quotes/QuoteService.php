<?php

namespace App\Services\Quotes;

use App\Actions\Quotes\AssignQuoteAction;
use App\Actions\Quotes\CreateQuoteRequestAction;
use App\Actions\Quotes\GetQuoteRequestsAction;
use App\Actions\Quotes\SendQuoteResponseAction;
use App\Actions\Quotes\UpdateQuoteStatusAction;
use App\Enums\QuoteResponseStatus;
use App\Enums\QuoteStatus;
use App\Models\QuoteActivity;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Mail;

class QuoteService
{
    public function __construct(
        protected CreateQuoteRequestAction $createQuoteRequestAction,
        protected SendQuoteResponseAction $sendQuoteResponseAction,
        protected GetQuoteRequestsAction $getQuoteRequestsAction,
        protected UpdateQuoteStatusAction $updateQuoteStatusAction,
        protected AssignQuoteAction $assignQuoteAction,
    ) {}

    /**
     * Create a new quote request.
     */
    public function createQuoteRequest(array $data): QuoteRequest
    {
        return $this->createQuoteRequestAction->execute($data);
    }

    /**
     * Send a quote response to a customer.
     */
    public function sendQuoteResponse(QuoteRequest $quoteRequest, array $data, int $sentBy): QuoteResponse
    {
        return $this->sendQuoteResponseAction->execute($quoteRequest, $data, $sentBy);
    }

    /**
     * Update a draft response revision in place.
     */
    public function updateDraftResponse(QuoteResponse $response, array $data): QuoteResponse
    {
        return $this->sendQuoteResponseAction->updateDraft($response, $data);
    }

    /**
     * Send a revision that was saved as a draft.
     */
    public function sendDraftResponse(QuoteRequest $quoteRequest, QuoteResponse $response, int $sentBy): QuoteResponse
    {
        return $this->sendQuoteResponseAction->sendDraft($quoteRequest, $response, $sentBy);
    }

    /**
     * Get paginated quote requests with filters.
     */
    public function getQuoteRequests(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->getQuoteRequestsAction->execute($filters, $perPage);
    }

    /**
     * Status counts for the manage list tabs.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        return $this->getQuoteRequestsAction->counts();
    }

    /**
     * KPI strip values for the manage list.
     *
     * @return array{avg_first_response_minutes: int|null, open: int, overdue: int}
     */
    public function stats(): array
    {
        return $this->getQuoteRequestsAction->stats();
    }

    /**
     * Get a single quote request by UUID.
     */
    public function getQuoteRequestByUuid(string $uuid): ?QuoteRequest
    {
        return QuoteRequest::with(['responses.sender', 'latestResponse.items'])
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Resolve a quote the given user is allowed to see.
     */
    public function findForUser(string $uuid, User $user): ?QuoteRequest
    {
        return QuoteRequest::query()
            ->accessibleBy($user)
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Load every relation the manage detail screen renders.
     */
    public function loadDetail(QuoteRequest $quote): QuoteRequest
    {
        return $quote->load([
            'assignedTo:id,name,email',
            'quotable',
            'tenant:id,name',
            'organization:id,name',
            'responses.sender:id,name,email',
            'responses.items',
            'activities.user:id,name,email',
        ]);
    }

    /**
     * Transition a quote request to a new status.
     */
    public function updateStatus(QuoteRequest $quoteRequest, string $status, ?User $actor = null, ?string $reason = null): QuoteRequest
    {
        return $this->updateQuoteStatusAction->execute($quoteRequest, $status, $actor, $reason);
    }

    /**
     * Assign (or unassign) a quote request.
     */
    public function assign(QuoteRequest $quoteRequest, ?User $assignee, User $actor): QuoteRequest
    {
        return $this->assignQuoteAction->execute($quoteRequest, $assignee, $actor);
    }

    /**
     * Append an internal note to the quote timeline.
     */
    public function addNote(QuoteRequest $quoteRequest, User $actor, string $body): QuoteActivity
    {
        return QuoteActivity::create([
            'quote_request_id' => $quoteRequest->id,
            'user_id' => $actor->id,
            'type' => QuoteActivity::NOTE,
            'meta' => ['body' => $body],
        ]);
    }

    /**
     * Re-queue the email for an already sent revision.
     */
    public function resendResponse(QuoteRequest $quoteRequest, QuoteResponse $response, User $actor): QuoteResponse
    {
        if ($response->status === QuoteResponseStatus::DRAFT) {
            throw new Exception('Draft revisions cannot be resent. Send them instead.');
        }

        Mail::to($quoteRequest->email)
            ->queue($this->sendQuoteResponseAction->mailableFor($quoteRequest, $response));

        QuoteActivity::create([
            'quote_request_id' => $quoteRequest->id,
            'user_id' => $actor->id,
            'type' => QuoteActivity::RESPONSE_SENT,
            'meta' => [
                'response_uuid' => $response->uuid,
                'revision' => $response->revision,
                'total_amount' => $response->total_amount,
                'currency' => $response->currency,
                'resend' => true,
            ],
        ]);

        return $response->fresh(['items', 'sender']);
    }

    /**
     * Get quote statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total' => QuoteRequest::count(),
            'pending' => QuoteRequest::where('status', QuoteStatus::PENDING)->count(),
            'in_progress' => QuoteRequest::where('status', QuoteStatus::IN_PROGRESS)->count(),
            'replied' => QuoteRequest::where('status', QuoteStatus::REPLIED)->count(),
            'accepted' => QuoteRequest::where('status', QuoteStatus::ACCEPTED)->count(),
            'declined' => QuoteRequest::where('status', QuoteStatus::DECLINED)->count(),
            'this_month' => QuoteRequest::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'this_week' => QuoteRequest::whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ])->count(),
        ];
    }
}
