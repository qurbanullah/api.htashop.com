<?php

namespace App\Policies;

use App\Models\ForumPost;
use App\Models\User;

class ForumPostPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, ForumPost $post): bool
    {
        if ($post->status->value === 'published') {
            return true;
        }

        if ($user && $user->hasAnyRole(['super-admin', 'admin'])) {
            return true;
        }

        return $user && $user->id === $post->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ForumPost $post): bool
    {
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return true;
        }

        return $user->id === $post->user_id
            && !$post->is_locked
            && in_array($post->status->value, ['published', 'pending']);
    }

    public function delete(User $user, ForumPost $post): bool
    {
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return true;
        }

        return $user->id === $post->user_id && !$post->is_locked;
    }

    public function moderate(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin']);
    }
}
