<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Support\StorePublicSupportTicketRequest;
use App\Http\Resources\V1\Ticket\TicketResource;
use App\Services\Tickets\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PublicSupportTicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService
    ) {
    }

    public function store(StorePublicSupportTicketRequest $request): JsonResponse
    {
        try {
            $ticket = $this->ticketService->createPublicSupportTicket($request->getTicketData());

            Log::info('Public support ticket created', [
                'ticket_id' => $ticket->id,
                'ticket_uuid' => $ticket->uuid,
                'guest_email' => $ticket->guest_email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Support ticket submitted successfully. Our team will review it shortly.',
                'data' => new TicketResource($ticket->load('reporter')),
            ], 201);
        } catch (\Throwable $throwable) {
            Log::error('Failed to create public support ticket', [
                'error' => $throwable->getMessage(),
                'request_data' => $request->except(['message']),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit your support request. Please try again later.',
            ], 500);
        }
    }
}
