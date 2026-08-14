<?php

namespace App\Http\Controllers\V1\Punchout;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Punchout\PunchoutStartRequest;
use App\Http\Resources\V1\Punchout\PunchoutSessionResource;
use App\Http\Responses\V1\ApiResponse;
use App\Http\Requests\V1\Punchout\PunchoutCartRequest;
use App\Http\Requests\V1\Punchout\PunchoutSetupRequest;
use App\Services\Punchout\PunchoutTransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

class PunchoutController extends Controller
{
    public function __construct(
        protected PunchoutTransactionService $punchoutTransactionService,
    ) {
    }

    public function setup(PunchoutSetupRequest $request, string $tenantIdentifier): Response
    {
        $responseData = $this->punchoutTransactionService->setup(
            $tenantIdentifier,
            $request->validated(),
            $request->getContent(),
            $request->query('protocol', $request->input('protocol'))
        );

        return response($responseData->content, $responseData->status)
            ->header('Content-Type', $responseData->contentType);
    }

    public function cart(PunchoutCartRequest $request, string $tenantIdentifier): Response
    {
        $responseData = $this->punchoutTransactionService->cart(
            $tenantIdentifier,
            $request->validated(),
            $request->getContent(),
            $request->query('protocol', $request->input('protocol'))
        );

        return response($responseData->content, $responseData->status)
            ->header('Content-Type', $responseData->contentType);
    }

    public function start(PunchoutStartRequest $request, string $tenantIdentifier): RedirectResponse|JsonResponse
    {
        $result = $this->punchoutTransactionService->start(
            $tenantIdentifier,
            $request->validated('session'),
            $request->validated('token'),
        );

        if ($result->redirectUrl) {
            return redirect()->away($result->redirectUrl);
        }

        return ApiResponse::success(
            new PunchoutSessionResource($result->session),
            'Punchout session resolved.'
        );
    }
}
