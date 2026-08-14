<?php

namespace App\Policies;

use App\Models\Manuscript;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ManuscriptPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the manuscript.
     */
    public function view(User $user, Manuscript $manuscript): bool
    {
        // Editors can view all manuscripts
        if ($this->isEditor($user)) {
            return true;
        }

        // Authors can view their own manuscripts
        return $this->isAuthor($user, $manuscript);
    }

    /**
     * Determine whether the user can update the manuscript.
     */
    public function update(User $user, Manuscript $manuscript): bool
    {
        // Editors can update all manuscripts
        if ($this->isEditor($user)) {
            return true;
        }

        // Authors can only update their manuscripts if in draft or revision_required status
        if ($this->isAuthor($user, $manuscript)) {
            return in_array($manuscript->status, ['draft', 'revision_required']);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the manuscript.
     */
    public function delete(User $user, Manuscript $manuscript): bool
    {
        // Only editors can delete manuscripts
        return $this->isEditor($user);
    }

    /**
     * Determine whether the user can withdraw the manuscript.
     */
    public function withdraw(User $user, Manuscript $manuscript): bool
    {
        // Only authors can withdraw their own manuscripts
        if (!$this->isAuthor($user, $manuscript)) {
            return false;
        }

        // Can only withdraw if manuscript is submitted and not already withdrawn or rejected
        return !in_array($manuscript->status, ['draft', 'withdrawn', 'rejected']);
    }

    /**
     * Determine whether the user can submit the manuscript for review.
     */
    public function submit(User $user, Manuscript $manuscript): bool
    {
        // Authors can submit their own draft manuscripts
        if ($this->isAuthor($user, $manuscript)) {
            return $manuscript->status === 'draft';
        }

        return false;
    }

    /**
     * Determine whether the user can approve the manuscript for review.
     */
    public function approveForReview(User $user, Manuscript $manuscript): bool
    {
        return $this->isEditor($user);
    }

    /**
     * Determine whether the user can desk reject the manuscript.
     */
    public function deskReject(User $user, Manuscript $manuscript): bool
    {
        return $this->isEditor($user);
    }

    /**
     * Check if user is an author of the manuscript.
     */
    protected function isAuthor(User $user, Manuscript $manuscript): bool
    {
        // Check if user is the submitting author
        if ($manuscript->submitting_author_id === $user->id) {
            return true;
        }

        // Check if user is the corresponding author through the relationship
        $correspondingAuthor = $manuscript->correspondingAuthor;
        if ($correspondingAuthor && $correspondingAuthor->user_id === $user->id) {
            return true;
        }

        // Check if user is in the authors list
        return $manuscript->authors()
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Check if user is an editor.
     */
    protected function isEditor(User $user): bool
    {
        return $user->roles()
            ->whereIn('name', ['editor_in_chief', 'editor-in-chief', 'eic', 'editor', 'admin'])
            ->exists();
    }
}
