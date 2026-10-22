<?php

namespace App\Http\Controllers\V1\Punchout;

use App\Http\Controllers\Controller;
use App\Services\Punchout\PunchoutInboundOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PunchoutOrderRequestController extends Controller
{
    public function __construct(
        protected PunchoutInboundOrderService $punchoutInboundOrderService,
    ) {}

    public function store(Request $request, string $tenantIdentifier): Response
    {
        try {
            $response = $this->punchoutInboundOrderService->handle(
                $tenantIdentifier,
                $request->getContent(),
                (string) $request->query('protocol', 'cxml'),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'order_request' => [$exception->getMessage()],
            ]);
        }

        return response($response->content, $response->status)
            ->header('Content-Type', $response->contentType);
    }
}
