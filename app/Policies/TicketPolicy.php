<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Determine whether the user can view any tickets.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view tickets (filtered by user_id in controller)
        return true;
    }

    /**
     * Determine whether the user can view the ticket.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        // Super admin and admin can view all tickets
        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        // Users can view their own tickets
        if ($user->id === $ticket->user_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create tickets.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create tickets
        return true;
    }

    /**
     * Determine whether the user can update the ticket.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        // Super admin and admin can update all tickets
        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        // Users can update their own tickets if open/pending and not locked
        if ($user->id === $ticket->user_id && !$ticket->is_locked && in_array($ticket->status, ['open', 'pending'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the ticket.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        // Only super admin and admin can delete tickets
        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        // Client and guest users can delete their own tickets if not resolved and not locked
        if ($user->hasRole(['client', 'guest']) && $user->id === $ticket->user_id && !$ticket->is_resolved && !$ticket->is_locked) {
            return true;
        }

        // Fallback to permission check for other users
        return $user->id === $ticket->user_id
            && $user->can('delete tickets')
            && !$ticket->is_resolved
            && !$ticket->is_locked;
    }

    /**
     * Determine whether the user can restore the ticket.
     */
    public function restore(User $user, Ticket $ticket): bool
    {
        // Only super admin and admin can restore tickets
        return $user->hasRole(['super-admin', 'admin']) && $user->can('edit tickets');
    }

    /**
     * Determine whether the user can permanently delete the ticket.
     */
    public function forceDelete(User $user, Ticket $ticket): bool
    {
        // Only super admin can force delete tickets
        return $user->hasRole('super-admin') && $user->can('delete tickets');
    }

    /**
     * Determine whether the user can assign tickets to others.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'manager']) && $user->can('assign tickets');
    }

    /**
     * Determine whether the user can unassign tickets.
     */
    public function unassign(User $user, Ticket $ticket): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'manager']) && $user->can('assign tickets');
    }

    /**
     * Determine whether the user can close tickets.
     */
    public function close(User $user, Ticket $ticket): bool
    {
        // Super admin and admin can close any ticket
        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        // Client and guest users can close their own tickets
        if ($user->hasRole(['client', 'guest']) && $user->id === $ticket->user_id) {
            return true;
        }

        // Fallback to permission check
        return $user->id === $ticket->user_id && $user->can('close tickets');
    }

    /**
     * Determine whether the user can resolve tickets.
     */
    public function resolve(User $user, Ticket $ticket): bool
    {
        // Only super admin and admin can resolve tickets
        return $user->hasRole(['super-admin', 'admin']) && $user->can('resolve tickets');
    }

    /**
     * Determine whether the user can archive tickets.
     */
    public function archive(User $user, Ticket $ticket): bool
    {
        // Only super admin and admin can archive tickets
        return $user->hasRole(['super-admin', 'admin']) && $user->can('archive tickets');
    }

    /**
     * Determine whether the user can change ticket status.
     */
    public function changeStatus(User $user, Ticket $ticket): bool
    {
        // Super admin and admin can change any ticket status
        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        // Client and guest users can change status of their own tickets if not locked
        if ($user->hasRole(['client', 'guest']) && $user->id === $ticket->user_id && !$ticket->is_locked) {
            return true;
        }

        // Fallback: Users can only change status of their own tickets and only if not locked
        return $user->id === $ticket->user_id && !$ticket->is_locked;
    }

    /**
     * Determine whether the user can lock/unlock tickets.
     */
    public function lock(User $user, Ticket $ticket): bool
    {
        // Only super admin and admin can lock/unlock tickets
        return $user->hasRole(['super-admin', 'admin']);
    }

    /**
     * Determine whether the user can view all tickets (admin view).
     */
    public function viewAll(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->can('view tickets');
    }

    /**
     * Determine whether the user can export tickets.
     */
    public function export(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->can('export data');
    }

    /**
     * Determine whether the user can view ticket analytics.
     */
    public function viewAnalytics(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->can('view analytics');
    }
}
