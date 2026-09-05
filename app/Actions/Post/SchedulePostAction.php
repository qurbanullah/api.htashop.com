<?php

namespace App\Actions\Post;

use App\Models\Post;

class SchedulePostAction
{
    public function execute(Post $post, \Carbon\Carbon $scheduledAt): Post
    {
        if (!$post->canBeSent()) {
            $this->throwDetailedValidationError($post);
        }

        if ($scheduledAt->isPast()) {
            throw new \Exception("Cannot schedule post for a past date. Provided date: {$scheduledAt->format('Y-m-d H:i:s')}, Current time: " . now()->format('Y-m-d H:i:s'));
        }

        $post->update([
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
        ]);

        return $post->fresh();
    }

    /**
     * Throw detailed validation error with specific reasons why post cannot be scheduled
     */
    private function throwDetailedValidationError(Post $post): void
    {
        $errors = [];

        // Check status
        if (!in_array($post->status, ['draft', 'scheduled'])) {
            $errors[] = "Status is '{$post->status}' (must be 'draft' or 'scheduled')";
        }

        // Check content
        if (empty($post->content)) {
            $errors[] = "Content is empty (post must have content)";
        }

        // Check title
        if (empty($post->title)) {
            $errors[] = "Title is missing";
        }

        // Additional helpful information
        $additionalInfo = [
            "Post ID: {$post->id}",
            "Post UUID: {$post->uuid}",
            "Current Status: {$post->status}",
            "Created: {$post->created_at->format('Y-m-d H:i:s')}",
            "Updated: {$post->updated_at->format('Y-m-d H:i:s')}"
        ];

        $errorMessage = "Post cannot be scheduled. Issues found:\n" .
                       "• " . implode("\n• ", $errors) . "\n\n" .
                       "Post Details:\n" .
                       "• " . implode("\n• ", $additionalInfo);

        throw new \Exception($errorMessage);
    }
}
