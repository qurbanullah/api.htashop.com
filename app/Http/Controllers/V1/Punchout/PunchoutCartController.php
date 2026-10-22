<?php

namespace App\Http\Controllers\V1\Punchout;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Punchout\PunchoutCartItemRequest;
use App\Http\Requests\V1\Punchout\PunchoutCartReturnRequest;
use App\Http\Responses\V1\ApiResponse;
use App\Models\PunchoutSession;
use App\Services\Punchout\PunchoutCartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PunchoutCartController extends Controller
{
    public function __construct(
        protected PunchoutCartService $punchoutCartService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->punchoutCartService->read($this->session($request)),
            'Punchout cart retrieved successfully',
        );
    }

    public function store(PunchoutCartItemRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->punchoutCartService->addItem($this->session($request), $request->validated()),
            'Item added to punchout cart',
            201,
        );
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        return ApiResponse::success(
            $this->punchoutCartService->updateItem($this->session($request), $uuid, (int) $data['quantity']),
            'Punchout cart updated successfully',
        );
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        return ApiResponse::success(
            $this->punchoutCartService->removeItem($this->session($request), $uuid),
            'Item removed from punchout cart',
        );
    }

    public function clear(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->punchoutCartService->clear($this->session($request)),
            'Punchout cart cleared',
        );
    }

    public function returnCart(PunchoutCartReturnRequest $request): Response
    {
        $response = $this->punchoutCartService->returnCart(
            $this->session($request),
            $request->validated('note'),
        );

        return response($response->content, $response->status)
            ->header('Content-Type', $response->contentType);
    }

    private function session(Request $request): PunchoutSession
    {
        $session = $request->attributes->get('punchout_session');

        abort_unless($session instanceof PunchoutSession, 500, 'Punchout session was not resolved.');

        return $session;
    }
}
