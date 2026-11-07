<?php

namespace App\Http\Controllers\V1\Shipment;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Shipment\StoreShipmentRequest;
use App\Http\Requests\V1\Shipment\UpdateShipmentRequest;
use App\Http\Requests\V1\Shipment\UpdateShipmentStatusRequest;
use App\Http\Resources\V1\Shipment\ShipmentResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Shipping\CarrierBookingService;
use App\Services\Shipping\ShipmentService;
use App\Support\Shipping\Carriers\Leopards\LeopardsException;
use Illuminate\Http\JsonResponse;

class ShipmentController extends Controller
{
    public function __construct(
        protected ShipmentService $shipments,
        protected CarrierBookingService $bookings,
    ) {}

    /**
     * The fulfilment plan for an order: existing shipments plus how much of
     * each line is still unshipped.
     */
    public function index(string $uuid): JsonResponse
    {
        $order = Order::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('plan', [Shipment::class, $order]);

        $plan = $this->shipments->planForOrder($order);

        return ApiResponse::success([
            'shipments' => ShipmentResource::collection($plan['shipments'])->resolve(),
            'items' => $plan['items'],
            'can_ship' => $plan['can_ship'],
        ], 'Shipments retrieved successfully');
    }

    public function store(StoreShipmentRequest $request, string $uuid): JsonResponse
    {
        $order = Order::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('create', [Shipment::class, $order]);

        $shipment = $this->shipments->create($order, $request->validated());

        return ApiResponse::success(
            new ShipmentResource($shipment),
            'Shipment created successfully',
            201,
        );
    }

    public function show(string $uuid): JsonResponse
    {
        $shipment = $this->shipments->show($uuid);
        $this->authorize('view', $shipment);

        return ApiResponse::success(
            new ShipmentResource($shipment),
            'Shipment retrieved successfully',
        );
    }

    public function update(UpdateShipmentRequest $request, string $uuid): JsonResponse
    {
        $shipment = $this->shipments->show($uuid);
        $this->authorize('update', $shipment);

        $shipment = $this->shipments->update($shipment, $request->validated());

        return ApiResponse::success(
            new ShipmentResource($shipment),
            'Shipment updated successfully',
        );
    }

    public function updateStatus(UpdateShipmentStatusRequest $request, string $uuid): JsonResponse
    {
        $shipment = $this->shipments->show($uuid);
        $this->authorize('update', $shipment);

        $shipment = $this->shipments->changeStatus($shipment, (string) $request->validated()['status']);

        return ApiResponse::success(
            new ShipmentResource($shipment),
            'Shipment status updated successfully',
        );
    }

    public function destroy(string $uuid): JsonResponse
    {
        $shipment = $this->shipments->show($uuid);
        $this->authorize('delete', $shipment);

        $this->shipments->delete($shipment);

        return ApiResponse::success(null, 'Shipment deleted successfully');
    }

    /**
     * The planner's suggested boxes for this shipment, without writing them.
     * Operators may adjust the result before applying it.
     */
    public function packingPlan(string $uuid): JsonResponse
    {
        $shipment = $this->shipments->show($uuid);
        $this->authorize('update', $shipment);

        return ApiResponse::success(
            $this->shipments->packingPlan($shipment),
            'Packing plan generated successfully',
        );
    }

    /** Replace the shipment's boxes with the planner's suggestion. */
    public function applyPlan(string $uuid): JsonResponse
    {
        $shipment = $this->shipments->show($uuid);
        $this->authorize('update', $shipment);

        $result = $this->shipments->applyPlan($shipment);

        return ApiResponse::success(
            [
                'shipment' => (new ShipmentResource($result['shipment']))->resolve(),
                'plan' => $result['plan'],
            ],
            'Packing plan applied successfully',
        );
    }

    /** Create the carrier consignment (TTN + label) for a packed shipment. */
    public function book(string $uuid): JsonResponse
    {
        $shipment = $this->shipments->show($uuid);
        $this->authorize('update', $shipment);

        try {
            $shipment = $this->bookings->book($shipment);
        } catch (LeopardsException $exception) {
            return ApiResponse::error($exception->getMessage(), null, 502);
        }

        return ApiResponse::success(
            new ShipmentResource($shipment),
            'Shipment booked with the carrier',
        );
    }

    /** Void the carrier consignment before it is dispatched. */
    public function cancelBooking(string $uuid): JsonResponse
    {
        $shipment = $this->shipments->show($uuid);
        $this->authorize('update', $shipment);

        try {
            $shipment = $this->bookings->cancelBooking($shipment);
        } catch (LeopardsException $exception) {
            return ApiResponse::error($exception->getMessage(), null, 502);
        }

        return ApiResponse::success(
            new ShipmentResource($shipment),
            'Carrier booking cancelled',
        );
    }
}
