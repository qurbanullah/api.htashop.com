<?php

declare(strict_types=1);

/**
 * CRUD operation class file.
 * php version 8.4
 *
 * @category  App\Services\Tickets
 *
 * @author    Qurban Ullah <qurbanullah@gmail.com>
 * @copyright 2024 Qurban Ullah - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Qurban Ullah <qurbanullah@gmail.com>, 2024
 * @license   CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @version   GIT: <git_id>
 *
 * @link      https://github.com/qurbanullah
 */

namespace App\Services\Tickets;

use App\Helpers\CacheHelper;
use App\Actions\Tickets\TicketCreateAction;
use App\Actions\Tickets\TicketDeleteAction;
use App\Actions\Tickets\TicketSearchByIdAction;
use App\Actions\Tickets\TicketSearchByUuidAction;
use App\Actions\Tickets\TicketUpdateAction;
use App\Actions\Tickets\TicketResolveAction;
use App\Actions\Tickets\TicketCloseAction;
use App\Actions\Tickets\TicketArchiveAction;
use App\Actions\Tickets\TicketLockAction;
use App\Actions\Tickets\TicketAllStatsAction;
use App\Actions\Tickets\TicketUserStatsAction;
use App\Enums\StatusEnum;
use App\Enums\PriorityEnum;
use App\Helpers\AdminHelper;
use App\Jobs\Tickets\SendTicketCreatedEmail;
use App\Jobs\Tickets\SendTicketResolvedEmail;
use App\Jobs\Tickets\SendTicketClosedEmail;
use App\Jobs\Tickets\SendTicketArchivedEmail;
use App\Jobs\Tickets\SendTicketLockedEmail;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Assignment\AssignmentService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CRUD operation class for Document Model.
 *
 * @category App\Services\Tickets
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class TicketService
{
    /**
     * Validate and update record in database.
     *
     * @param  array  $request  data to update
     */
    public function create(array $data): Ticket
    {
        $result = (new TicketCreateAction)->handle($data);
        $this->_clearCaching($result);

        // Auto-assign to support assistant with least active tickets
        $this->autoAssignTicket($result);

        // Dispatch email notification job (asynchronous)
        SendTicketCreatedEmail::dispatch($result->load('reporter'));

        return $result;
    }

    /**
     * Create a support ticket submitted from the public website.
     */
    public function createPublicSupportTicket(array $data): Ticket
    {
        $result = DB::transaction(function () use ($data) {
            return Ticket::create([
                'uuid' => data_get($data, 'uuid'),
                'user_id' => data_get($data, 'user_id'),
                'guest_name' => data_get($data, 'guest_name'),
                'guest_email' => data_get($data, 'guest_email'),
                'title' => data_get($data, 'title'),
                'slug' => data_get($data, 'slug'),
                'stype' => data_get($data, 'stype'),
                'severity' => data_get($data, 'severity'),
                'reproducibility' => data_get($data, 'reproducibility'),
                'priority' => data_get($data, 'priority'),
                'status' => data_get($data, 'status', StatusEnum::OPEN->value),
                'is_visible' => data_get($data, 'is_visible', true),
                'is_resolved' => data_get($data, 'is_resolved', false),
                'is_locked' => data_get($data, 'is_locked', false),
                'is_archived' => data_get($data, 'is_archived', false),
                'description' => data_get($data, 'description'),
                'steps_to_reproduce' => data_get($data, 'steps_to_reproduce'),
                'additional_information' => data_get($data, 'additional_information'),
                'resolved_on' => data_get($data, 'resolved_on'),
                'archived_on' => data_get($data, 'archived_on'),
            ]);
        });

        $this->_clearCaching($result);
        $this->autoAssignTicket($result);
        SendTicketCreatedEmail::dispatch($result->load('reporter'));

        return $result;
    }

    /**
     * Auto-assign ticket to support assistant with the least active tickets.
     *
     * @param  Ticket  $ticket
     * @return void
     */
    protected function autoAssignTicket(Ticket $ticket): void
    {
        try {
            // Get all support staff using AdminHelper (cached and no duplicates)
            $supportUsers = AdminHelper::getSupportStaff();

            if ($supportUsers->isEmpty()) {
                return; // No support users available
            }

            // Find user with least active ticket assignments
            $userWithLeastTickets = $supportUsers->sortBy(function ($user) {
                return $user->activeAssignments()
                    ->where('assignable_type', 'App\Models\Ticket')
                    ->count();
            })->first();

            if ($userWithLeastTickets) {
                $assignmentService = new AssignmentService();
                $assignmentService->assign(
                    $ticket,
                    $userWithLeastTickets,
                    'Auto-assigned on ticket creation'
                );
            }
        } catch (\Exception $e) {
            // Silently fail auto-assignment - ticket creation should not fail
            Log::warning('Failed to auto-assign ticket', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Validate and update record in database.
     *
     * @param  array  $data  data to update
     * @param  int  $id  model id
     */
    public function update(array $data, int $id): Ticket
    {
        $result = (new TicketUpdateAction)->handle($data, $id);
        $this->_clearCaching($result);

        return $result;
    }

    /**
     * Delete record in database.
     *
     * @param  int  $id  model id
     */
    public function delete(int $id): bool
    {
        $result = (new TicketDeleteAction)->handle($id);
        CacheHelper::forget([], 'cached_Tickets');

        // Clear statistics cache when tickets are deleted
        $this->clearStatsCache();

        return $result;
    }

    /**
     * Search record in database.
     *
     * @param  int  $id  model id
     */
    public function searchById(int $id): Ticket
    {
        return CacheHelper::remember([],
            'cached_ticket_'.$id,
            3600, // Cache for 1 hour
            fn() => (new TicketSearchByIdAction)->handle($id)
        );
    }

    /**
     * Search record in database by UUID.
     * Returns the Ticket or null if not found.
     *
     * @param  string  $uuid  ticket UUID
     */
    public function searchByUuid(string $uuid): ?Ticket
    {
        return Cache::remember(
            'cached_ticket_'.$uuid,
            3600, // Cache for 1 hour
            fn() => (new TicketSearchByUuidAction)->handle($uuid)
        );
    }

    /**
     * Get all ticket statistics (optimized single query approach)
     *
     * @return array
     */
    public function getAllStatistics(): array
    {
        $stats = CacheHelper::remember([], 'ticket_all_stats', 300, function () {
            return (new TicketAllStatsAction)->handle();
        });

        // Ensure all numeric values are integers (handle Redis string serialization)
        if (isset($stats['totalTickets'])) {
            $stats['totalTickets'] = (int) $stats['totalTickets'];
        }
        if (isset($stats['statusStats'])) {
            foreach ($stats['statusStats'] as $key => $value) {
                $stats['statusStats'][$key] = (int) $value;
            }
        }
        if (isset($stats['priorityStats'])) {
            foreach ($stats['priorityStats'] as $key => $value) {
                $stats['priorityStats'][$key] = (int) $value;
            }
        }
        if (isset($stats['categoryStats'])) {
            foreach ($stats['categoryStats'] as $key => $value) {
                $stats['categoryStats'][$key] = (int) $value;
            }
        }

        return $stats;
    }

    /**
     * Get ticket statistics by status
     *
     * @return array
     */
    public function getStatusStatistics(): array
    {
        return $this->getAllStatistics()['statusStats'];
    }

    /**
     * Get ticket statistics by priority
     *
     * @return array
     */
    public function getPriorityStatistics(): array
    {
        return $this->getAllStatistics()['priorityStats'];
    }

    /**
     * Get ticket statistics by category (stype)
     *
     * @return array
     */
    public function getCategoryStatistics(): array
    {
        return $this->getAllStatistics()['categoryStats'];
    }

    /**
     * Get total ticket count
     *
     * @return int
     */
    public function getTotalTickets(): int
    {
        return $this->getAllStatistics()['totalTickets'];
    }

    /**
     * Clear statistics cache
     *
     * @return void
     */
    public function clearStatsCache(): void
    {
        CacheHelper::forget([], 'ticket_all_stats');
        // Keep individual cache keys for backward compatibility if needed
        CacheHelper::forget([], 'ticket_status_stats');
        CacheHelper::forget([], 'ticket_priority_stats');
        CacheHelper::forget([], 'ticket_category_stats');
        CacheHelper::forget([], 'total_tickets_count');
    }

    /**
     * Get comprehensive admin statistics and analytics.
     *
     * @param array $filters Optional filters (start_date, end_date)
     * @return array
     */
    public function getAdminStats(array $filters = []): array
    {
        $query = Ticket::query();

        // Apply date range filter if provided
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        // Get basic counts
        $total = $query->count();
        $open = (clone $query)->where('status', StatusEnum::OPEN->value)->count();
        $closed = (clone $query)->where('status', StatusEnum::CLOSED->value)->count();
        $resolved = (clone $query)->where('status', StatusEnum::RESOLVED->value)->count();
        $archived = (clone $query)->where('status', StatusEnum::ARCHIVED->value)->count();

        // Get counts by priority
        $highPriority = (clone $query)->where('priority', PriorityEnum::HIGH->value)->count();
        $urgentPriority = (clone $query)->where('priority', PriorityEnum::URGENT->value)->count();

        // Get counts by status (cast enum to string)
        $byStatus = (clone $query)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->status->value => $item->count];
            })
            ->toArray();

        // Get counts by priority (cast enum to string)
        $byPriority = (clone $query)
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->priority->value => $item->count];
            })
            ->toArray();

        // Get counts by category (stype) - cast enum to string
        $byCategory = (clone $query)
            ->selectRaw('stype, COUNT(*) as count')
            ->whereNotNull('stype')
            ->groupBy('stype')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->stype->value ?? $item->stype => $item->count];
            })
            ->toArray();

        // Get trend data (last 30 days)
        $trendData = (clone $query)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'count' => $item->count,
                ];
            })
            ->toArray();

        // Get recent tickets
        $recentTickets = (clone $query)
            ->select(['id', 'uuid', 'title', 'status', 'priority', 'stype', 'created_at', 'user_id'])
            ->with(['reporter:id,name,email'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function ($ticket) {
                $ticketArray = $ticket->toArray();
                // Map reporter to user for frontend compatibility
                $ticketArray['user'] = $ticketArray['reporter'] ?? null;
                unset($ticketArray['reporter']);
                return $ticketArray;
            })
            ->toArray();

        // Calculate average response time (in hours) - mock for now
        $avgResponseTime = 4.2;

        // Calculate resolution rate
        $resolutionRate = $total > 0 ? ($resolved / $total) * 100 : 0;

        // Calculate customer satisfaction - mock for now
        $customerSatisfaction = 4.6;

        return [
            'totals' => [
                'total' => $total,
                'open' => $open,
                'closed' => $closed,
                'resolved' => $resolved,
                'archived' => $archived,
                'high_priority' => $highPriority,
                'urgent_priority' => $urgentPriority,
            ],
            'by_status' => $byStatus,
            'by_priority' => $byPriority,
            'by_category' => $byCategory,
            'trend_data' => $trendData,
            'recent_tickets' => $recentTickets,
            'metrics' => [
                'avg_response_time_hours' => $avgResponseTime,
                'resolution_rate' => round($resolutionRate, 1),
                'customer_satisfaction' => $customerSatisfaction,
            ],
        ];
    }

    /**
     * Get user-specific ticket statistics (optimized single query approach)
     *
     * @param int $userId
     * @return array
     */
    public function getUserStatistics(int $userId): array
    {
        return CacheHelper::remember([], "ticket_user_{$userId}_stats", 300, function () use ($userId) {
            $stats = (new TicketUserStatsAction)->handle($userId);

            // Add backward compatibility mapping
            $stats['total'] = $stats['totalTickets'];
            $stats['open'] = $stats['statusStats']['open'];
            $stats['closed'] = $stats['statusStats']['closed'];
            $stats['resolved'] = $stats['statusStats']['resolved'];
            $stats['archived'] = $stats['statusStats']['archived'];

            return $stats;
        });
    }

    /**
     * Get user-specific ticket statistics by status
     *
     * @param int $userId
     * @return array
     */
    public function getUserStatusStatistics(int $userId): array
    {
        $stats = $this->getUserStatistics($userId)['statusStats'];

        // Ensure all required keys exist with default values
        return array_merge([
            'open' => 0,
            'closed' => 0,
            'resolved' => 0,
            'archived' => 0,
        ], $stats ?? []);
    }

    /**
     * Get user-specific ticket statistics by priority
     *
     * @param int $userId
     * @return array
     */
    public function getUserPriorityStatistics(int $userId): array
    {
        return $this->getUserStatistics($userId)['priorityStats'];
    }

    /**
     * Get user-specific ticket statistics by category (stype)
     *
     * @param int $userId
     * @return array
     */
    public function getUserCategoryStatistics(int $userId): array
    {
        return $this->getUserStatistics($userId)['categoryStats'];
    }

    /**
     * Get user-specific total ticket count
     *
     * @param int $userId
     * @return int
     */
    public function getUserTotalTickets(int $userId): int
    {
        return $this->getUserStatistics($userId)['totalTickets'];
    }

    /**
     * Clear user-specific statistics cache
     *
     * @param int $userId
     * @return void
     */
    public function clearUserStatsCache(int $userId): void
    {
        CacheHelper::forget([], "ticket_user_{$userId}_stats");
    }

    /**
     * Resolve ticket.
     *
     * @param  int  $id  model id
     */
    public function resolve(int $id): Ticket
    {
        $result = (new TicketResolveAction)->handle($id);
        $this->_clearCaching($result);

        // Dispatch email notification job (asynchronous)
        SendTicketResolvedEmail::dispatch($result);

        return $result;
    }

    /**
     * Close ticket.
     *
     * @param  int  $id  model id
     */
    public function close(int $id): Ticket
    {
        $result = (new TicketCloseAction)->handle($id);
        $this->_clearCaching($result);

        // Dispatch email notification job (asynchronous)
        SendTicketClosedEmail::dispatch($result);

        return $result;
    }

    /**
     * Archive ticket.
     *
     * @param  int  $id  model id
     */
    public function archive(int $id): Ticket
    {
        $result = (new TicketArchiveAction)->handle($id);
        $this->_clearCaching($result);

        // Dispatch email notification job (asynchronous)
        SendTicketArchivedEmail::dispatch($result);

        return $result;
    }

    /**
     * Lock or unlock ticket.
     *
     * @param  int  $id  model id
     * @param  bool  $isLocked  whether to lock or unlock the ticket
     */
    public function lock(int $id, bool $isLocked = true): Ticket
    {
        $result = (new TicketLockAction)->handle($id, $isLocked);
        $this->_clearCaching($result);

        // Dispatch email notification job (asynchronous)
        SendTicketLockedEmail::dispatch($result, $isLocked);

        return $result;
    }

    /**
     * Clear caching after update.
     *
     * @param  \App\Models\Ticket  $ticket  data to update
     */
    private function _clearCaching(Ticket $ticket): void
    {
        CacheHelper::forget([], 'cached_tickets');
        CacheHelper::forget([], 'cached_ticket_'.$ticket->id);
        CacheHelper::forget([], 'cached_ticket_'.$ticket->uuid); // Clear UUID-based cache used by frontend
        CacheHelper::forget([], 'cached_ticket'.$ticket->slug);
        CacheHelper::forget([], 'form_key_'.$ticket->slug);

        // Clear statistics cache when tickets are modified
        $this->clearStatsCache();

        // Clear user-specific stats cache for the ticket owner
        if ($ticket->user_id) {
            $this->clearUserStatsCache($ticket->user_id);
        }
    }
}
