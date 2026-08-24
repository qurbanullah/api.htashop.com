<?php

namespace App\Http\Controllers\V1\Gdpr;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Gdpr\StoreGdprConsentRequest;
use App\Http\Resources\V1\Gdpr\GdprConsentResource;
use App\Http\Responses\V1\ApiResponse;
use App\Services\Gdpr\GdprConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GdprConsentController extends Controller
{
    public function __construct(
        protected GdprConsentService $consentService
    ) {}

    /**
     * Record a GDPR consent choice.
     * Public endpoint — guests are identified by a pseudonymous consent token,
     * authenticated users are linked via their bearer token when present.
     *
     * POST /api/v1/gdpr/consents
     */
    public function store(StoreGdprConsentRequest $request): JsonResponse
    {
        $consent = $this->consentService->record(
            consentToken: $request->validated('consent_token'),
            categories: $request->validated('categories'),
            policyVersion: $request->validated('policy_version'),
            source: $request->validated('source'),
            userId: $this->authenticatedUserId($request),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return ApiResponse::success(
            new GdprConsentResource($consent->load('user')),
            'Consent recorded successfully',
            201
        );
    }

    /**
     * Get the most recent consent for a token (and/or authenticated user).
     *
     * GET /api/v1/gdpr/consents/latest?consent_token=...
     */
    public function latest(Request $request): JsonResponse
    {
        $consentToken = (string) $request->query('consent_token', '');

        if ($consentToken === '') {
            return ApiResponse::error('A consent token is required', null, 422);
        }

        $consent = $this->consentService->latest(
            $consentToken,
            $this->authenticatedUserId($request)
        );

        if (!$consent) {
            return ApiResponse::success(null, 'No consent recorded yet', 200);
        }

        return ApiResponse::success(
            new GdprConsentResource($consent->load('user')),
            'Latest consent retrieved successfully'
        );
    }

    /**
     * Get the authenticated user's consent history (privacy center).
     *
     * GET /api/v1/gdpr/consents (auth required)
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);

        $consents = \App\Models\GdprConsent::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('accepted_at', 'desc')
            ->paginate($perPage);

        return ApiResponse::success(
            GdprConsentResource::collection($consents)->response()->getData(true),
            'Consent history retrieved successfully'
        );
    }

    /**
     * Withdraw consent — deletes recorded choices for the token/user.
     *
     * DELETE /api/v1/gdpr/consents?consent_token=...
     */
    public function destroy(Request $request): JsonResponse
    {
        $consentToken = (string) $request->query('consent_token', '');

        if ($consentToken === '') {
            return ApiResponse::error('A consent token is required', null, 422);
        }

        $deleted = $this->consentService->withdraw(
            $consentToken,
            $this->authenticatedUserId($request)
        );

        return ApiResponse::success(
            ['deleted' => $deleted],
            'Consent withdrawn successfully'
        );
    }

    /**
     * Best-effort: resolve the authenticated user on a public route.
     * Returns null for guests.
     */
    protected function authenticatedUserId(Request $request): ?int
    {
        return $request->user('api')?->id;
    }
}
