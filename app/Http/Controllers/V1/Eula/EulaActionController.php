<?php

namespace App\Http\Controllers\V1\Eula;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Eula\EulaResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Eula;
use App\Services\Eula\EulaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EulaActionController extends Controller
{
    public function __construct(
        protected EulaService $eulaService
    ) {}

    /**
     * Activate a EULA.
     */
    public function activate(Request $request, Eula $eula): JsonResponse
    {
        $this->authorize('update', $eula);

        $deactivateOthers = $request->input('deactivate_others', true);

        $activatedEula = $this->eulaService->activate($eula, $deactivateOthers);

        return ApiResponse::success(
            new EulaResource($activatedEula),
            'EULA activated successfully'
        );
    }
}
