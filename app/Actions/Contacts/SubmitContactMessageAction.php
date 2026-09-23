<?php

namespace App\Actions\Contacts;

use App\Jobs\Contacts\SendContactMessageNotificationJob;
use App\Models\ContactMessage;
use App\Services\Messages\ContactMessageService;

class SubmitContactMessageAction
{
    public function __construct(
        private ContactMessageService $contactMessageService
    ) {}

    public function execute(array $data): ContactMessage
    {
        $cleanData = $this->validateAndCleanData($data);
        $contactMessage = $this->contactMessageService->store($cleanData);
        SendContactMessageNotificationJob::dispatch($contactMessage);

        return $contactMessage;
    }

    private function validateAndCleanData(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'phone' => isset($data['phone']) ? trim($data['phone']) : null,
            'order_uuid' => $data['order_uuid'] ?? null,
            'subject' => trim($data['subject']),
            'message' => trim($data['message']),
            'status' => 'new',
            'tenant_id' => $data['tenant_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'metadata' => isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [],
        ];
    }
}
