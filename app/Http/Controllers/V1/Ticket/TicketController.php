<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Ticket;

use App\Actions\Tickets\TicketShowAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Ticket\TicketStoreRequest;
use App\Http\Requests\V1\Ticket\TicketUpdateRequest;
use App\Http\Resources\V1\Ticket\TicketResource;
use App\Jobs\Tickets\SendTicketReopenedEmail;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketService;
use App\Services\Assignment\AssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService,
        protected AssignmentService $assignmentService
    ) {
        // Exclude 'show' and 'update' from automatic authorization due to custom parameters
        $this->authorizeResource(Ticket::class, 'ticket', [
            'except' => ['show', 'update']
        ]);
    }

    /**
     * Display a listing of the user's tickets.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->input('filters', []);

            // Filter by authenticated user
            $filters['user_id'] = $request->user()->id;

            $data = (new TicketShowAction)->handle([
                'rows_per_page' => $request->input('rows', 15),
                'filters' => $filters,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => TicketResource::collection($data->items()),
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tickets',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created ticket.
     */
    public function store(TicketStoreRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();



            // (no debug logging here)

            // Extract relationship IDs
            $softwareIds = $validated['software_ids'] ?? [];
            $versionIds = $validated['version_ids'] ?? [];
            $ltypeIds = $validated['ltype_ids'] ?? [];
            $packageIds = $validated['package_ids'] ?? [];

            // Remove relationship arrays from main data
            unset($validated['software_ids'], $validated['version_ids'], $validated['ltype_ids'], $validated['package_ids']);

            // Create ticket through service
            $ticket = $this->ticketService->create($validated);

            // Sync polymorphic relationships
            if (!empty($softwareIds)) {
                $ticket->softwares()->sync($softwareIds);
            }
            if (!empty($versionIds)) {
                $ticket->versions()->sync($versionIds);
            }
            if (!empty($ltypeIds)) {
                $ticket->ltypes()->sync($ltypeIds);
            }
            if (!empty($packageIds)) {
                $ticket->packages()->sync($packageIds);
            }

            return response()->json([
                'success' => true,
                'message' => 'Ticket created successfully',
                'data' => new TicketResource($ticket->load([
                    'softwares',
                    'versions',
                    'ltypes',
                    'packages',
                ])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified ticket by UUID.
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $ticket = $this->ticketService->searchByUuid($uuid);

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found',
                ], 404);
            }

            // Manual authorization since excluded from authorizeResource
            $this->authorize('view', $ticket);

            $ticket->load([
                'reporter',
                'softwares',
                'versions',
                'ltypes',
                'packages',
                'messages',
            ]);

            return response()->json([
                'success' => true,
                'data' => new TicketResource($ticket),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'This action is unauthorized.',
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified ticket.
     */
    public function update(TicketUpdateRequest $request, int $id): JsonResponse
    {
        try {
            $ticket = $this->ticketService->searchById($id);

            // Manual authorization since excluded from authorizeResource
            $this->authorize('update', $ticket);

            $validated = $request->validated();

            // Debug: log the incoming description to verify what the client sent during update
            if (array_key_exists('description', $validated)) {
                Log::debug('TicketController::update description payload', [
                    'ticket_id' => $id,
                    'description_snippet' => substr($validated['description'], 0, 2000),
                ]);
                Log::debug('TicketController::update contains_img', [
                    'ticket_id' => $id,
                    'contains_img' => (strpos($validated['description'], '<img') !== false),
                ]);
            } else {
                Log::debug('TicketController::update no description provided', ['ticket_id' => $id]);
            }

            // Additional debug: log which keys were validated
            Log::debug('TicketController::update validated keys', [
                'ticket_id' => $id,
                'keys' => array_keys($validated)
            ]);

            // Log client-side flag if present
            if (array_key_exists('client_contains_img', $validated)) {
                Log::debug('TicketController::update client_contains_img', [
                    'ticket_id' => $id,
                    'client_contains_img' => (bool) $validated['client_contains_img']
                ]);
            }

            // Extract relationship IDs
            $softwareIds = $validated['software_ids'] ?? null;
            $versionIds = $validated['version_ids'] ?? null;
            $ltypeIds = $validated['ltype_ids'] ?? null;
            $packageIds = $validated['package_ids'] ?? null;

            // Remove relationship arrays from main data
            unset($validated['software_ids'], $validated['version_ids'], $validated['ltype_ids'], $validated['package_ids']);

            // Update ticket through service
            $ticket = $this->ticketService->update($validated, $id);

            // Sync polymorphic relationships if provided
            if ($softwareIds !== null) {
                $ticket->softwares()->sync($softwareIds);
            }
            if ($versionIds !== null) {
                $ticket->versions()->sync($versionIds);
            }
            if ($ltypeIds !== null) {
                $ticket->ltypes()->sync($ltypeIds);
            }
            if ($packageIds !== null) {
                $ticket->packages()->sync($packageIds);
            }

            return response()->json([
                'success' => true,
                'message' => 'Ticket updated successfully',
                'data' => new TicketResource($ticket->load([
                    'softwares',
                    'versions',
                    'ltypes',
                    'packages',
                ])),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'This action is unauthorized.',
            ], 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified ticket.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $ticket = $this->ticketService->searchById($id);

            // Policy authorization
            $this->authorize('delete', $ticket);

            // Delete ticket through service
            $this->ticketService->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Ticket deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this ticket',
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get ticket statistics for the authenticated user.
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $stats = $this->ticketService->getUserStatusStatistics($userId);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get comprehensive admin ticket statistics and analytics.
     *
     * GET /api/v1/admin/tickets/stats
     */
    public function adminStats(Request $request): JsonResponse
    {
        try {
            $stats = $this->ticketService->getAdminStats($request->all());

            return response()->json([
                'success' => true,
                'data' => $stats,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all tickets for admin (no user filter).
     *
     * GET /api/v1/admin/tickets
     */
    public function adminIndex(Request $request): JsonResponse
    {
        try {
            // Don't filter by user_id for admin
            $data = (new TicketShowAction)->handle([
                'rows_per_page' => $request->input('rows', 15),
                'filters' => $request->input('filters', []),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => TicketResource::collection($data->items()),
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tickets',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload a file used in ticket descriptions (returns public URL)
     */
    public function upload(Request $request, string $uuid): JsonResponse
    {
        try {
            // Allow uploads for new tickets during creation by passing a special uuid 'new'.
            $ticket = null;
            if ($uuid !== 'new') {
                $ticket = $this->ticketService->searchByUuid($uuid);
                if (!$ticket) {
                    return response()->json(['success' => false, 'message' => 'Ticket not found'], 404);
                }
            }

            if (!$request->hasFile('file')) {
                return response()->json(['success' => false, 'message' => 'No file uploaded'], 422);
            }

            $file = $request->file('file');

            // Basic size guard
            if ($file->getSize() > 25 * 1024 * 1024) {
                return response()->json(['success' => false, 'message' => 'File too large'], 422);
            }

            $path = $file->store('tickets', 'public');
            $url = request()->getSchemeAndHttpHost() . '/storage/' . ltrim($path, '/');

            return response()->json([
                'success' => true,
                'message' => 'File uploaded',
                'data' => ['url' => $url],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolve a ticket (mark as resolved).
     *
     * PATCH /api/v1/tickets/{uuid}/resolve
     */
    public function resolve(string $uuid): JsonResponse
    {
        try {
            $ticket = $this->ticketService->searchByUuid($uuid);

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found',
                ], 404);
            }

            $this->authorize('update', $ticket);

            $ticket = $this->ticketService->resolve($ticket->id);

            return response()->json([
                'success' => true,
                'message' => 'Ticket resolved successfully',
                'data' => new TicketResource($ticket->fresh()),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to resolve this ticket',
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Close a ticket.
     *
     * PATCH /api/v1/tickets/{uuid}/close
     */
    public function closeTicket(string $uuid): JsonResponse
    {
        try {
            $ticket = $this->ticketService->searchByUuid($uuid);

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found',
                ], 404);
            }

            $this->authorize('update', $ticket);

            $ticket = $this->ticketService->close($ticket->id);

            return response()->json([
                'success' => true,
                'message' => 'Ticket closed successfully',
                'data' => new TicketResource($ticket->fresh()),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to close this ticket',
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to close ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reopen a ticket (set status to open).
     *
     * PATCH /api/v1/tickets/{uuid}/reopen
     */
    public function reopen(string $uuid): JsonResponse
    {
        try {
            $ticket = $this->ticketService->searchByUuid($uuid);

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found',
                ], 404);
            }

            $this->authorize('update', $ticket);

            // Update ticket status to open
            $ticket = $this->ticketService->update([
                'status' => 'open',
                'is_resolved' => false,
                'resolved_on' => null,
            ], $ticket->id);

            // Dispatch email notification job (asynchronous)
            SendTicketReopenedEmail::dispatch($ticket);

            return response()->json([
                'success' => true,
                'message' => 'Ticket reopened successfully',
                'data' => new TicketResource($ticket->fresh()),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to reopen this ticket',
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reopen ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lock or unlock a ticket.
     *
     * PATCH /api/v1/tickets/{uuid}/lock
     */
    public function toggleLock(string $uuid): JsonResponse
    {
        try {
            $ticket = $this->ticketService->searchByUuid($uuid);

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found',
                ], 404);
            }

            $this->authorize('update', $ticket);

            $newLockState = !$ticket->is_locked;
            $ticket = $this->ticketService->lock($ticket->id, $newLockState);

            return response()->json([
                'success' => true,
                'message' => $newLockState ? 'Ticket locked successfully' : 'Ticket unlocked successfully',
                'data' => new TicketResource($ticket->fresh()),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to lock/unlock this ticket',
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle lock status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Archive a ticket.
     *
     * PATCH /api/v1/tickets/{uuid}/archive
     */
    public function archiveTicket(string $uuid): JsonResponse
    {
        try {
            $ticket = $this->ticketService->searchByUuid($uuid);

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found',
                ], 404);
            }

            $this->authorize('update', $ticket);

            $ticket = $this->ticketService->archive($ticket->id);

            return response()->json([
                'success' => true,
                'message' => 'Ticket archived successfully',
                'data' => new TicketResource($ticket->fresh()),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to archive this ticket',
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to archive ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of users that can be assigned tickets (support-assistant role and above).
     *
     * GET /api/v1/tickets/assignable-users
     */
    public function assignableUsers(): JsonResponse
    {
        try {
            $users = $this->assignmentService->getAssignableUsers();

            return response()->json([
                'success' => true,
                'data' => $users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'avatar' => $user->avatar,
                        'active_tickets_count' => $user->activeAssignments()
                            ->where('assignable_type', 'App\Models\Ticket')
                            ->count(),
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get assignable users',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign a ticket to a user.
     *
     * POST /api/v1/tickets/{uuid}/assign
     */
    public function assign(Request $request, string $uuid): JsonResponse
    {
        try {
            $ticket = $this->ticketService->searchByUuid($uuid);

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found',
                ], 404);
            }

            $this->authorize('update', $ticket);

            $validated = $request->validate([
                'assigned_to' => 'required|integer|exists:users,id',
                'notes' => 'nullable|string|max:1000',
            ]);

            $assignee = User::findOrFail($validated['assigned_to']);
            $assignment = $this->assignmentService->assign(
                $ticket,
                $assignee,
                $validated['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Ticket assigned successfully',
                'data' => [
                    'assignment' => $assignment,
                    'ticket' => new TicketResource($ticket->fresh()),
                ],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to assign this ticket',
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Unassign a ticket.
     *
     * POST /api/v1/tickets/{uuid}/unassign
     */
    public function unassign(string $uuid): JsonResponse
    {
        try {
            $ticket = $this->ticketService->searchByUuid($uuid);

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found',
                ], 404);
            }

            $this->authorize('update', $ticket);

            $this->assignmentService->unassign($ticket);

            return response()->json([
                'success' => true,
                'message' => 'Ticket unassigned successfully',
                'data' => new TicketResource($ticket->fresh()),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to unassign this ticket',
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unassign ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
