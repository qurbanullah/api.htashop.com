<?php

namespace App\Http\Controllers\V1\Contact;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Contact\ReplyContactMessageRequest;
use App\Http\Resources\V1\Contact\ContactMessageCollection;
use App\Http\Resources\V1\Contact\ContactMessageResource;
use App\Http\Responses\V1\ApiResponse;
use App\Jobs\Contacts\SendContactReplyEmail;
use App\Services\Messages\ContactMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContactMessageAdminController extends Controller
{
    public function __construct(
        private ContactMessageService $contactMessageService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $query = $this->contactMessageService->search(
                $request->input('search'),
                $request->input('status')
            );

            $messages = $query->paginate((int) $request->input('per_page', 15));

            return ApiResponse::success(
                new ContactMessageCollection($messages),
                'Contact messages retrieved successfully'
            );
        } catch (\Throwable $throwable) {
            Log::error('Failed to retrieve contact messages', [
                'error' => $throwable->getMessage(),
            ]);

            return ApiResponse::error('Failed to retrieve contact messages', null, 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $message = $this->contactMessageService->findVisible($id);

            if (!$message) {
                return ApiResponse::error('Contact message not found', null, 404);
            }

            if ($message->status === 'new') {
                $this->contactMessageService->markAsRead($message);
                $message->refresh();
            }

            return ApiResponse::success(
                new ContactMessageResource($message),
                'Contact message retrieved successfully'
            );
        } catch (\Throwable $throwable) {
            Log::error('Failed to retrieve contact message', [
                'contact_message_id' => $id,
                'error' => $throwable->getMessage(),
            ]);

            return ApiResponse::error('Failed to retrieve contact message', null, 500);
        }
    }

    public function statistics(): JsonResponse
    {
        try {
            return ApiResponse::success(
                $this->contactMessageService->getStatistics(),
                'Contact message statistics retrieved successfully'
            );
        } catch (\Throwable $throwable) {
            Log::error('Failed to retrieve contact message statistics', [
                'error' => $throwable->getMessage(),
            ]);

            return ApiResponse::error('Failed to retrieve contact message statistics', null, 500);
        }
    }

    public function markAsRead(int $id): JsonResponse
    {
        try {
            $message = $this->contactMessageService->findVisible($id);

            if (!$message) {
                return ApiResponse::error('Contact message not found', null, 404);
            }

            $this->contactMessageService->markAsRead($message);
            $message->refresh();

            return ApiResponse::success(
                new ContactMessageResource($message),
                'Contact message marked as read'
            );
        } catch (\Throwable $throwable) {
            Log::error('Failed to mark contact message as read', [
                'contact_message_id' => $id,
                'error' => $throwable->getMessage(),
            ]);

            return ApiResponse::error('Failed to mark contact message as read', null, 500);
        }
    }

    public function reply(int $id, ReplyContactMessageRequest $request): JsonResponse
    {
        try {
            $message = $this->contactMessageService->findVisible($id);

            if (!$message) {
                return ApiResponse::error('Contact message not found', null, 404);
            }

            $replyMessage = $request->input('reply_message');
            $replySubject = $request->input('reply_subject', 'Re: ' . $message->subject);
            $repliedBy = $request->user()->id;

            $this->contactMessageService->markAsReplied($message->id, $replyMessage, $repliedBy);

            SendContactReplyEmail::dispatch(
                $message,
                $replyMessage,
                $replySubject,
                $request->user()->name,
                $request->user()->email,
                $repliedBy,
                false // status is already marked as replied
            );

            $message->refresh();

            Log::info('Contact message reply sent', [
                'contact_message_id' => $message->id,
                'replied_by' => $repliedBy,
            ]);

            return ApiResponse::success(
                new ContactMessageResource($message),
                'Reply sent successfully'
            );
        } catch (\Throwable $throwable) {
            Log::error('Failed to reply to contact message', [
                'contact_message_id' => $id,
                'error' => $throwable->getMessage(),
            ]);

            return ApiResponse::error('Failed to send reply', null, 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $message = $this->contactMessageService->findVisible($id);

            if (!$message) {
                return ApiResponse::error('Contact message not found', null, 404);
            }

            $this->contactMessageService->delete($message);

            return ApiResponse::success(null, 'Contact message deleted successfully');
        } catch (\Throwable $throwable) {
            Log::error('Failed to delete contact message', [
                'contact_message_id' => $id,
                'error' => $throwable->getMessage(),
            ]);

            return ApiResponse::error('Failed to delete contact message', null, 500);
        }
    }
}
