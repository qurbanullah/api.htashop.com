<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Contact;

use App\Actions\Contacts\SubmitContactMessageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Contact\StoreContactMessageRequest;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ContactMessageController extends Controller
{
    public function store(
        StoreContactMessageRequest $request,
        SubmitContactMessageAction $submitContactMessageAction
    ): JsonResponse {
        try {
            $data = $request->getContactData();
            $data['tenant_id'] = TenantContext::publicTenantId($request);
            $data['user_id'] = $request->user('api')?->id;

            $contactMessage = $submitContactMessageAction->execute($data);

            Log::info('Public contact message submitted', [
                'contact_message_id' => $contactMessage->id,
                'email' => $contactMessage->email,
                'subject' => $contactMessage->subject,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Your message has been sent successfully. We will get back to you soon.',
                'data' => [
                    'id' => $contactMessage->id,
                    'uuid' => $contactMessage->uuid,
                    'status' => $contactMessage->status,
                ],
            ], 201);
        } catch (\Throwable $throwable) {
            Log::error('Failed to submit public contact message', [
                'error' => $throwable->getMessage(),
                'request_data' => $request->except(['message']),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send your message. Please try again later.',
            ], 500);
        }
    }
}
