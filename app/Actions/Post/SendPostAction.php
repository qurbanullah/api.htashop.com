<?php

namespace App\Actions\Post;

use App\Enums\PostTypeEnum;
use App\Models\Post;
use App\Models\Subscribe;
use App\Models\User;
use App\Mail\Post\PostMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendPostAction
{
    /**
     * Send post to subscribers
     */
    public function execute(Post $post, array $options = []): array
    {
        if (!$post->canBeSent()) {
            $this->throwDetailedValidationError($post);
        }

        // Get recipients based on criteria
        $recipients = $this->getRecipients($post, $post->recipients ?? []);

        if ($recipients->isEmpty()) {
            throw new \Exception('No subscribers found to send post to.');
        }

        // Update post status to sending
        $post->update([
            'status' => 'sending',
            'recipients_count' => $recipients->count(),
        ]);

        $sentCount = 0;
        $failedCount = 0;

        // Send emails to subscribers
        foreach ($recipients as $subscriber) {
            try {
                Mail::to($subscriber->email)
                    ->queue(new PostMail($post, $subscriber));

                $sentCount++;

                Log::info("Queued post '{$post->title}' to {$subscriber->email}");

            } catch (\Exception $e) {
                $failedCount++;
                Log::error("Failed to queue post '{$post->title}' to {$subscriber->email}: " . $e->getMessage());
            }
        }

        // Update final post status and statistics
        $post->update([
            'status' => 'sent',
            'sent_at' => now(),
            'sent_count' => $sentCount,
        ]);

        // Log summary
        Log::info("Post '{$post->title}' sending completed. Sent: {$sentCount}, Failed: {$failedCount}");

        if ($failedCount > 0) {
            Log::warning("Post '{$post->title}' had {$failedCount} failed sends out of " . $recipients->count() . " total recipients.");
        }

        return [
            'success' => true,
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'total_recipients' => $recipients->count()
        ];
    }

    private function getRecipients(Post $post, array $criteria): \Illuminate\Support\Collection
    {
        $type = $post->type instanceof PostTypeEnum
            ? $post->type->value
            : (string) $post->type;

        // Newsletter posts go to the guest + account newsletter list
        // (Subscribe rows with type = newsletter).
        if ($type === PostTypeEnum::NEWSLETTER->value) {
            return Subscribe::query()
                ->where('type', 'newsletter')
                ->where('is_subscribed', true)
                ->whereNotNull('email')
                ->get();
        }

        $query = User::query();

        // Default to subscribed users if no criteria specified
        if (empty($criteria)) {
            return $query->whereHas('subscriptions', function ($q) {
                $q->where('type', 'post')
                  ->where('is_subscribed', true);
            })->get();
        }

        // Add filtering based on criteria
        foreach ($criteria as $criterion => $value) {
            switch ($criterion) {
                case 'roles':
                    if (!empty($value)) {
                        $query->whereHas('roles', function ($q) use ($value) {
                            $q->whereIn('name', $value);
                        });
                    }
                    break;
                case 'created_after':
                    if ($value) {
                        $query->where('created_at', '>=', Carbon::parse($value));
                    }
                    break;
                case 'active_only':
                    if ($value) {
                        $query->whereNotNull('email_verified_at');
                    }
                    break;
                case 'subscribed_only':
                    if ($value) {
                        $query->whereHas('subscriptions', function ($q) {
                            $q->where('type', 'post')
                              ->where('is_subscribed', true);
                        });
                    }
                    break;
            }
        }

        // Always ensure users are subscribed unless explicitly disabled
        if (!isset($criteria['subscribed_only']) || $criteria['subscribed_only']) {
            $query->whereHas('subscriptions', function ($q) {
                $q->where('type', 'post')
                  ->where('is_subscribed', true);
            });
        }

        return $query->get();
    }

    /**
     * Throw detailed validation error with specific reasons why post cannot be sent
     */
    private function throwDetailedValidationError(Post $post): void
    {
        $errors = [];
        $statusLabel = $post->status instanceof \App\Enums\PostStatusEnum
            ? $post->status->value
            : (string) $post->status;

        // Check status
        if (!in_array($statusLabel, ['draft', 'scheduled'])) {
            $errors[] = "Status is '{$statusLabel}' (must be 'draft' or 'scheduled')";
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
            "Current Status: {$statusLabel}",
            "Created: {$post->created_at->format('Y-m-d H:i:s')}",
            "Updated: {$post->updated_at->format('Y-m-d H:i:s')}"
        ];

        if ($post->scheduled_at) {
            $additionalInfo[] = "Scheduled At: {$post->scheduled_at->format('Y-m-d H:i:s')}";
        }

        $errorMessage = "Post cannot be sent. Issues found:\n" .
                       "• " . implode("\n• ", $errors) . "\n\n" .
                       "Post Details:\n" .
                       "• " . implode("\n• ", $additionalInfo);

        throw new \Exception($errorMessage);
    }
}
