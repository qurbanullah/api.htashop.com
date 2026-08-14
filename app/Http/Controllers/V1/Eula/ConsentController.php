<?php

namespace App\Http\Controllers\V1\Eula;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Eula\RecordConsentRequest;
use App\Http\Resources\V1\Eula\ConsentResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Eula;
use App\Services\Eula\ConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsentController extends Controller
{
    public function __construct(
        protected ConsentService $consentService
    ) {}

    /**
     * Record user consent for a EULA.
     */
    public function store(RecordConsentRequest $request): JsonResponse
    {
        $eula = Eula::where('uuid', $request->input('eula_uuid'))->firstOrFail();

        // Check if already consented
        $hasConsented = $this->consentService->hasConsented(
            $request->user()->id,
            $eula->id,
            $request->input('consentable_type'),
            $request->input('consentable_id')
        );

        if ($hasConsented) {
            return ApiResponse::error(
                'Consent already recorded',
                null,
                409
            );
        }

        $consent = $this->consentService->recordConsent(
            userId: $request->user()->id,
            eulaId: $eula->id,
            consentableType: $request->input('consentable_type'),
            consentableId: $request->input('consentable_id'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            metadata: $request->input('metadata', [])
        );

        return ApiResponse::success(
            new ConsentResource($consent),
            'Consent recorded successfully',
            201
        );
    }

    /**
     * Check if user has consented to the latest EULA.
     */
    public function check(Request $request): JsonResponse
    {
        $softwareId = $request->input('software_id');
        $versionId = $request->input('version_id');

        $hasConsented = $this->consentService->hasConsentedToLatest(
            $request->user()->id,
            $softwareId,
            $versionId
        );

        return ApiResponse::success(
            ['has_consented' => $hasConsented],
            $hasConsented ? 'User has consented' : 'User consent required'
        );
    }

    /**
     * Get user's consent history.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);

        $consents = $this->consentService->getUserConsents(
            $request->user()->id,
            $perPage
        );

        return ApiResponse::success(
            ConsentResource::collection($consents)->response()->getData(true),
            'Consents retrieved successfully'
        );
    }

    /**
     * Get consents for a specific EULA (admin only).
     */
    public function eulaConsents(Request $request, Eula $eula): JsonResponse
    {
        $this->authorize('viewAny', Eula::class);

        $perPage = $request->input('per_page', 15);

        $consents = $this->consentService->getEulaConsents($eula->id, $perPage);

        return ApiResponse::success(
            ConsentResource::collection($consents)->response()->getData(true),
            'EULA consents retrieved successfully'
        );
    }

    /**
     * Get consent statistics for a EULA (admin only).
     */
    public function eulaConsentStats(Eula $eula): JsonResponse
    {
        $this->authorize('viewAny', Eula::class);

        $stats = $this->consentService->getEulaConsentStats($eula);

        return ApiResponse::success(
            $stats,
            'EULA consent statistics retrieved successfully'
        );
    }
}
