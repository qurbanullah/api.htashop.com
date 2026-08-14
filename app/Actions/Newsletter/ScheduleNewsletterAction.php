<?php

namespace App\Actions\Newsletter;

use App\Models\Newsletter;

class ScheduleNewsletterAction
{
    public function execute(Newsletter $newsletter, \Carbon\Carbon $scheduledAt): Newsletter
    {
        if (!$newsletter->canBeSent()) {
            $this->throwDetailedValidationError($newsletter);
        }

        if ($scheduledAt->isPast()) {
            throw new \Exception("Cannot schedule newsletter for a past date. Provided date: {$scheduledAt->format('Y-m-d H:i:s')}, Current time: " . now()->format('Y-m-d H:i:s'));
        }

        $newsletter->update([
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
        ]);

        return $newsletter->fresh();
    }

    /**
     * Throw detailed validation error with specific reasons why newsletter cannot be scheduled
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

        $errorMessage = "Newsletter cannot be scheduled. Issues found:\n" .
                       "• " . implode("\n• ", $errors) . "\n\n" .
                       "Newsletter Details:\n" .
                       "• " . implode("\n• ", $additionalInfo);

        throw new \Exception($errorMessage);
    }
}
