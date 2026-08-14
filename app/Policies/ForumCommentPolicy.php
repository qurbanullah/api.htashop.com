<?php

namespace App\Policies;

use App\Models\ForumComment;
use App\Models\User;

class ForumCommentPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ForumComment $comment): bool
    {
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return true;
        }

        return $user->id === $comment->user_id
            && $comment->status->value === 'visible';
    }

    public function delete(User $user, ForumComment $comment): bool
    {
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return true;
        }

        return $user->id === $comment->user_id;
    }
}
