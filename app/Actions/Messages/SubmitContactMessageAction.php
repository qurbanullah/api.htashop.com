<?php

namespace App\Actions\Messages;

use App\Jobs\User\SendContactMessageNotificationJob;
use App\Models\ContactMessage;
use App\Services\Messages\ContactMessageService;

class SubmitContactMessageAction
{
    public function __construct(
        private ContactMessageService $contactMessageService
    ) {}

    /**
     * Execute the contact message submission
     */
    public function execute(array $data): ContactMessage
    {
        // Validate and clean data
        $cleanData = $this->validateAndCleanData($data);

        // Store the contact message
        $contactMessage = $this->contactMessageService->store($cleanData);

        // Dispatch email notification job
        SendContactMessageNotificationJob::dispatch($contactMessage);

        return $contactMessage;
    }

    /**
     * Validate and clean the input data
     */
    private function validateAndCleanData(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'phone' => isset($data['phone']) ? trim($data['phone']) : null,
            'subject' => trim($data['subject']),
            'message' => trim($data['message']),
            'status' => 'new',
            'metadata' => isset($data['metadata']) && is_array($data['metadata'])
                ? $data['metadata']
                : [],
        ];
    }
}
