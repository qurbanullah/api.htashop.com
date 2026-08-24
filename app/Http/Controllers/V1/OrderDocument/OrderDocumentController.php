<?php

namespace App\Http\Controllers\V1\OrderDocument;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Document\DocumentResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Order;
use App\Services\Order\OrderService;
use App\Services\Pdf\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderDocumentController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected PdfService $pdfService,
    ) {
    }

    public function index(string $orderUuid): JsonResponse
    {
        $order = $this->orderService->show($orderUuid);

        return ApiResponse::success(
            DocumentResource::collection($order->documents()->orderByDesc('created_at')->get()),
            'Order documents retrieved successfully',
        );
    }

    public function store(Request $request, string $orderUuid): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:invoice,packing_slip'],
        ]);

        $order = $this->orderService->show($orderUuid);
        $document = $this->pdfService->generateForOrder($order, $data['type']);

        return ApiResponse::success(
            new DocumentResource($document),
            'Document generated successfully',
            201,
        );
    }
}
