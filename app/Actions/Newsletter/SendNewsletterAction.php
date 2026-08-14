<?php

namespace App\Actions\Newsletter;

use App\Models\Newsletter;
use App\Models\User;
use App\Mail\NewsletterMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendNewsletterAction
{
    /**
     * Send newsletter to subscribers
     */
    public function execute(Newsletter $newsletter, array $options = []): array
    {
        if (!$newsletter->canBeSent()) {
            $this->throwDetailedValidationError($newsletter);
        }

        // Get recipients based on criteria
        $recipients = $this->getRecipients($newsletter->recipients ?? []);

        if ($recipients->isEmpty()) {
            throw new \Exception('No subscribers found to send newsletter to.');
        }

        // Update newsletter status to sending
        $newsletter->update([
            'status' => 'sending',
            'recipients_count' => $recipients->count(),
        ]);

        $sentCount = 0;
        $failedCount = 0;

        // Send emails to subscribers
        foreach ($recipients as $subscriber) {
            try {
                Mail::to($subscriber->email)
                    ->queue(new NewsletterMail($newsletter, $subscriber));

                $sentCount++;

                Log::info("Queued newsletter '{$newsletter->title}' to {$subscriber->email}");

            } catch (\Exception $e) {
                $failedCount++;
                Log::error("Failed to queue newsletter '{$newsletter->title}' to {$subscriber->email}: " . $e->getMessage());
            }
        }

        // Update final newsletter status and statistics
        $newsletter->update([
            'status' => 'sent',
            'sent_at' => now(),
            'sent_count' => $sentCount,
        ]);

        // Log summary
        Log::info("Newsletter '{$newsletter->title}' sending completed. Sent: {$sentCount}, Failed: {$failedCount}");

        if ($failedCount > 0) {
            Log::warning("Newsletter '{$newsletter->title}' had {$failedCount} failed sends out of " . $recipients->count() . " total recipients.");
        }

        return [
            'success' => true,
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'total_recipients' => $recipients->count()
        ];
    }

    private function getRecipients(array $criteria): \Illuminate\Database\Eloquent\Collection
    {
        $query = User::query();

        // Default to subscribed users if no criteria specified
        if (empty($criteria)) {
            return $query->whereHas('subscriptions', function ($q) {
                $q->where('type', 'newsletter')
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
                            $q->where('type', 'newsletter')
                              ->where('is_subscribed', true);
                        });
                    }
                    break;
            }
        }

        // Always ensure users are subscribed unless explicitly disabled
        if (!isset($criteria['subscribed_only']) || $criteria['subscribed_only']) {
            $query->whereHas('subscriptions', function ($q) {
                $q->where('type', 'newsletter')
                  ->where('is_subscribed', true);
            });
        }

        return $query->get();
    }

    /**
     * Throw detailed validation error with specific reasons why newsletter cannot be sent
     */
    private function throwDetailedValidationError(Newsletter $newsletter): void
    {
        $errors = [];

        // Check status
        if (!in_array($newsletter->status, ['draft', 'scheduled'])) {
            $errors[] = "Status is '{$newsletter->status}' (must be 'draft' or 'scheduled')";
        }

        // Check content
        if (empty($newsletter->content)) {
            $errors[] = "Content is empty (newsletter must have content)";
        }

        // Check title
        if (empty($newsletter->title)) {
            $errors[] = "Title is missing";
        }

        // Additional helpful information
        $additionalInfo = [
            "Newsletter ID: {$newsletter->id}",
            "Newsletter UUID: {$newsletter->uuid}",
            "Current Status: {$newsletter->status}",
            "Created: {$newsletter->created_at->format('Y-m-d H:i:s')}",
            "Updated: {$newsletter->updated_at->format('Y-m-d H:i:s')}"
        ];

        if ($newsletter->scheduled_at) {
            $additionalInfo[] = "Scheduled At: {$newsletter->scheduled_at->format('Y-m-d H:i:s')}";
        }

        $errorMessage = "Newsletter cannot be sent. Issues found:\n" .
                       "• " . implode("\n• ", $errors) . "\n\n" .
                       "Newsletter Details:\n" .
                       "• " . implode("\n• ", $additionalInfo);

        throw new \Exception($errorMessage);
    }
}
