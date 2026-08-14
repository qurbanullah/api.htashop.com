<?php

namespace App\Policies;

use App\Models\Feedback;
use App\Models\User;

class FeedbackPolicy
{
    /**
     * Determine if the user can view any feedbacks.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can view the feedback.
     */
    public function view(User $user, Feedback $feedback): bool
    {
        // Admin can view all, users can view their own
        return $user->hasRole('admin') || $user->email === $feedback->email;
    }

    /**
     * Determine if the user can reply to the feedback.
     */
    public function reply(User $user, Feedback $feedback): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can update the feedback.
     */
    public function update(User $user, Feedback $feedback): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can delete the feedback.
     */
    public function delete(User $user, Feedback $feedback): bool
    {
        return $user->hasRole('admin');
    }
}
