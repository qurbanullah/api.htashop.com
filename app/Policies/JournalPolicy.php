<?php

namespace App\Policies;

use App\Models\Journal;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class JournalPolicy
{
    /**
     * Determine whether the user can view any journals.
     * Public access - anyone can view journals list
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the journal.
     * Public access - anyone can view a journal
     */
    public function view(?User $user, Journal $journal): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create journals.
     * Only admins and users with editor-in-chief role can create journals
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin', 'editor-in-chief']);
    }

    /**
     * Determine whether the user can update the journal.
     * Admins, journal's editorial board members, and editor-in-chief can update
     */
    public function update(User $user, Journal $journal): bool
    {
        // Admins can update any journal
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        // Check if user is part of the journal's editorial board
        $isEditorialBoardMember = $journal->editorialBoard()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        if ($isEditorialBoardMember) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the journal.
     * Only admins and editor-in-chief of the journal can delete
     */
    public function delete(User $user, Journal $journal): bool
    {
        // Admins can delete any journal
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        // Check if user is editor-in-chief of this journal
        $isEditorInChief = $journal->editorialBoard()
            ->where('user_id', $user->id)
            ->wherePivot('role', 'editor_in_chief')
            ->where('is_active', true)
            ->exists();

        return $isEditorInChief;
    }

    /**
     * Determine whether the user can restore the journal.
     * Only admins can restore deleted journals
     */
    public function restore(User $user, Journal $journal): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    /**
     * Determine whether the user can permanently delete the journal.
     * Only super-admins can force delete
     */
    public function forceDelete(User $user, Journal $journal): bool
    {
        return $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can manage the editorial board.
     * Admins and editor-in-chief of the journal can manage editorial board
     */
    public function manageEditorialBoard(User $user, Journal $journal): bool
    {
        // Admins can manage any journal's editorial board
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        // Check if user is editor-in-chief of this journal
        $isEditorInChief = $journal->editorialBoard()
            ->where('user_id', $user->id)
            ->wherePivot('role', 'editor_in_chief')
            ->where('is_active', true)
            ->exists();

        return $isEditorInChief;
    }

    /**
     * Determine whether the user can manage submissions for this journal.
     * Editorial board members can manage submissions
     */
    public function manageSubmissions(User $user, Journal $journal): bool
    {
        // Admins can manage any journal's submissions
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        // Check if user is part of the journal's editorial board with appropriate role
        $isEditorialBoardMember = $journal->editorialBoard()
            ->where('user_id', $user->id)
            ->whereIn('journal_editorial_board.role', [
                'editor_in_chief',
                'associate_editor',
                'managing_editor'
            ])
            ->where('is_active', true)
            ->exists();

        return $isEditorialBoardMember;
    }
}
