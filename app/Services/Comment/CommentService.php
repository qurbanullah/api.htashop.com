<?php

namespace App\Services\Comment;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommentService
{
    /**
     * Get comments for a commentable entity
     */
    public function getComments($commentable, array $filters = []): Collection
    {
        $query = $commentable->comments()
            ->with(['user.academicProfile', 'replies.user'])
            ->when(isset($filters['is_internal']), function ($q) use ($filters) {
                $q->where('is_internal', $filters['is_internal']);
            })
            ->topLevel() // Only get parent comments; replies are loaded via relationship
            ->orderBy('created_at', 'desc');

        return $query->get();
    }

    /**
     * Get a specific comment
     */
    public function getComment(int $commentId): Comment
    {
        return Comment::with([
            'user.academicProfile',
            'commentable',
            'parent',
            'replies.user'
        ])->findOrFail($commentId);
    }

    /**
     * Create a comment
     */
    public function createComment(
        $commentable,
        User $user,
        string $content,
        array $options = []
    ): Comment {
        return DB::transaction(function () use ($commentable, $user, $content, $options) {
            $comment = $commentable->comments()->create([
                'user_id' => $user->id,
                'content' => $content,
                'parent_id' => $options['parent_id'] ?? null,
                'is_internal' => $options['is_internal'] ?? false,
                'attachments' => $options['attachments'] ?? null,
                'metadata' => $options['metadata'] ?? [],
            ]);

            // Attach revision contexts if provided
            if (!empty($options['revision_ids'])) {
                $comment->syncRevisions($options['revision_ids']);
            }

            Log::info('Comment created', [
                'comment_id' => $comment->id,
                'user_id' => $user->id,
                'commentable_type' => get_class($commentable),
                'commentable_id' => $commentable->id,
                'revision_ids' => $options['revision_ids'] ?? null,
            ]);

            // TODO: Send notification to relevant users

            return $comment->load(['user.academicProfile', 'replies.user', 'revisions']);
        });
    }

    /**
     * Reply to a comment
     */
    public function replyToComment(
        Comment $parentComment,
        User $user,
        string $content,
        array $options = []
    ): Comment {
        return $this->createComment(
            $parentComment->commentable,
            $user,
            $content,
            array_merge($options, ['parent_id' => $parentComment->id])
        );
    }

    /**
     * Update a comment
     */
    public function updateComment(Comment $comment, User $user, array $data): Comment
    {
        if ($comment->user_id !== $user->id) {
            throw new \Exception('You are not authorized to update this comment');
        }

        $comment->update([
            'content' => $data['content'] ?? $comment->content,
            'attachments' => $data['attachments'] ?? $comment->attachments,
        ]);

        return $comment->fresh(['user.academicProfile', 'replies.user']);
    }

    /**
     * Delete a comment
     */
    public function deleteComment(Comment $comment, User $user): bool
    {
        if ($comment->user_id !== $user->id) {
            throw new \Exception('You are not authorized to delete this comment');
        }

        return $comment->delete();
    }

    /**
     * Mark comment as read
     */
    public function markAsRead(Comment $comment): Comment
    {
        $comment->markAsRead();
        return $comment;
    }

    /**
     * Mark all comments as read for a user on a specific entity
     */
    public function markAllAsRead($commentable, User $user): int
    {
        return Comment::where('commentable_type', get_class($commentable))
            ->where('commentable_id', $commentable->id)
            ->where('user_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    /**
     * Get unread comments count for a user on a specific entity
     */
    public function getUnreadCount($commentable, User $user): int
    {
        return Comment::where('commentable_type', get_class($commentable))
            ->where('commentable_id', $commentable->id)
            ->where('user_id', '!=', $user->id)
            ->where('is_read', false)
            ->count();
    }
}
