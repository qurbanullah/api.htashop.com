<?php

namespace App\Http\Controllers\V1\Quote;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Quote\QuoteAssignRequest;
use App\Http\Requests\V1\Quote\QuoteIndexRequest;
use App\Http\Requests\V1\Quote\QuoteNoteRequest;
use App\Http\Requests\V1\Quote\QuoteResponseStoreRequest;
use App\Http\Requests\V1\Quote\QuoteStatusUpdateRequest;
use App\Http\Resources\V1\Document\DocumentResource;
use App\Http\Resources\V1\Quote\QuoteActivityResource;
use App\Http\Resources\V1\Quote\QuoteRequestDetailResource;
use App\Http\Resources\V1\Quote\QuoteRequestResource;
use App\Http\Resources\V1\Quote\QuoteResponseResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Membership;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\User;
use App\Services\Pdf\PdfService;
use App\Services\Quotes\QuoteService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ManageQuoteController extends Controller
{
    public function __construct(
        protected QuoteService $quoteService,
        protected PdfService $pdfService,
    ) {}

    /**
     * Paginated, scoped quote list with status counts for the tabs.
     */
    public function index(QuoteIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 20);

        $paginator = $this->quoteService->getQuoteRequests($filters, $perPage);

        return ApiResponse::success([
            'data' => QuoteRequestResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'counts' => $this->quoteService->counts(),
        ], 'Quotes retrieved successfully');
    }

    /**
     * KPI strip: first-response average, open and overdue counts.
     */
    public function stats(): JsonResponse
    {
        return ApiResponse::success(
            $this->quoteService->stats(),
            'Quote statistics retrieved successfully',
        );
    }

    /**
     * Team members that can be assigned a quote.
     */
    public function assignees(Request $request): JsonResponse
    {
        $user = $request->user();
        $columns = ['id', 'name', 'email'];

        if ($user->isAdmin()) {
            $users = User::query()->orderBy('name')->limit(200)->get($columns);
        } else {
            $organizationIds = $user->activeOrganizationIds();
            $tenantIds = $user->activeTenantIds();

            $userIds = Membership::query()
                ->where('is_active', true)
                ->when(
                    $organizationIds !== [] || $tenantIds !== [],
                    fn (Builder $query) => $query->where(function (Builder $inner) use ($organizationIds, $tenantIds): void {
                        if ($organizationIds !== []) {
                            $inner->whereIn('organization_id', $organizationIds);
                        }

                        if ($tenantIds !== []) {
                            $inner->orWhereIn('tenant_id', $tenantIds);
                        }
                    }),
                    fn (Builder $query) => $query->where('user_id', $user->id),
                )
                ->pluck('user_id')
                ->push($user->id)
                ->unique()
                ->values()
                ->all();

            $users = User::query()
                ->whereIn('id', $userIds)
                ->orderBy('name')
                ->get($columns);
        }

        return ApiResponse::success($users, 'Quote assignees retrieved successfully');
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $quote = $this->resolveQuote($request, $uuid);
        $this->authorize('view', $quote);

        return ApiResponse::success(
            new QuoteRequestDetailResource($this->quoteService->loadDetail($quote)),
            'Quote retrieved successfully',
        );
    }

    public function updateStatus(QuoteStatusUpdateRequest $request, string $uuid): JsonResponse
    {
        $quote = $this->resolveQuote($request, $uuid);
        $this->authorize('update', $quote);

        $data = $request->validated();

        $quote = $this->quoteService->updateStatus(
            $quote,
            (string) $data['status'],
            $request->user(),
            $data['reason'] ?? null,
        );

        return ApiResponse::success(
            new QuoteRequestDetailResource($this->quoteService->loadDetail($quote)),
            'Quote status updated successfully',
        );
    }

    public function assign(QuoteAssignRequest $request, string $uuid): JsonResponse
    {
        $quote = $this->resolveQuote($request, $uuid);
        $this->authorize('assign', $quote);

        $assignee = $request->validated('user_id')
            ? User::query()->findOrFail($request->validated('user_id'))
            : null;

        $quote = $this->quoteService->assign($quote, $assignee, $request->user());

        return ApiResponse::success(
            new QuoteRequestDetailResource($this->quoteService->loadDetail($quote)),
            $assignee ? 'Quote assigned successfully' : 'Quote unassigned successfully',
        );
    }

    /**
     * Create a new reply revision (draft or sent).
     */
    public function storeResponse(QuoteResponseStoreRequest $request, string $uuid): JsonResponse
    {
        $quote = $this->resolveQuote($request, $uuid);
        $this->authorize('reply', $quote);

        $response = $this->quoteService->sendQuoteResponse(
            $quote,
            $request->validated(),
            $request->user()->id,
        );

        return ApiResponse::success(
            new QuoteResponseResource($response),
            $response->isDraft() ? 'Quote draft saved successfully' : 'Quote response sent successfully',
            201,
        );
    }

    /**
     * Edit a draft revision before it is sent.
     */
    public function updateResponse(QuoteResponseStoreRequest $request, string $uuid, string $response): JsonResponse
    {
        $quote = $this->resolveQuote($request, $uuid);
        $this->authorize('reply', $quote);

        $draft = $this->resolveResponse($quote, $response);

        return ApiResponse::success(
            new QuoteResponseResource(
                $this->quoteService->updateDraftResponse($draft, $request->validated()),
            ),
            'Quote draft updated successfully',
        );
    }

    /**
     * Send a revision that was previously saved as a draft.
     */
    public function sendDraft(Request $request, string $uuid, string $response): JsonResponse
    {
        $quote = $this->resolveQuote($request, $uuid);
        $this->authorize('reply', $quote);

        $draft = $this->resolveResponse($quote, $response);

        return ApiResponse::success(
            new QuoteResponseResource(
                $this->quoteService->sendDraftResponse($quote, $draft, $request->user()->id),
            ),
            'Quote response sent successfully',
        );
    }

    /**
     * Re-queue the email for an already sent revision.
     */
    public function resend(Request $request, string $uuid, string $response): JsonResponse
    {
        $quote = $this->resolveQuote($request, $uuid);
        $this->authorize('reply', $quote);

        $revision = $this->resolveResponse($quote, $response);

        return ApiResponse::success(
            new QuoteResponseResource(
                $this->quoteService->resendResponse($quote, $revision, $request->user()),
            ),
            'Quote response queued for resend',
        );
    }

    /**
     * Render (or refresh) the quotation PDF for a revision.
     */
    public function quotationPdf(Request $request, string $uuid, string $response): JsonResponse
    {
        $quote = $this->resolveQuote($request, $uuid);
        $this->authorize('view', $quote);

        $revision = $this->resolveResponse($quote, $response);

        try {
            $document = $this->pdfService->generateForQuote($quote, $revision);
        } catch (RuntimeException $e) {
            return ApiResponse::error(
                'The PDF service is unavailable. Please try again shortly.',
                ['error' => config('app.debug') ? $e->getMessage() : null],
                503,
            );
        }

        return ApiResponse::success(
            new DocumentResource($document),
            'Quotation PDF generated successfully',
            201,
        );
    }

    /**
     * Append an internal note (never emailed to the customer).
     */
    public function storeNote(QuoteNoteRequest $request, string $uuid): JsonResponse
    {
        $quote = $this->resolveQuote($request, $uuid);
        $this->authorize('update', $quote);

        $activity = $this->quoteService->addNote(
            $quote,
            $request->user(),
            (string) $request->validated('body'),
        );

        return ApiResponse::success(
            new QuoteActivityResource($activity->load('user:id,name,email')),
            'Note added successfully',
            201,
        );
    }

    private function resolveQuote(Request $request, string $uuid): QuoteRequest
    {
        $quote = $this->quoteService->findForUser($uuid, $request->user());

        abort_if($quote === null, 404, 'Quote request not found.');

        return $quote;
    }

    private function resolveResponse(QuoteRequest $quote, string $uuid): QuoteResponse
    {
        $response = $quote->responses()->where('uuid', $uuid)->first();

        abort_if($response === null, 404, 'Quote response not found.');

        return $response->load(['items', 'sender:id,name,email']);
    }
}
