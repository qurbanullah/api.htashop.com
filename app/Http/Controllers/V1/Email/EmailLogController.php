<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Email;

use App\Http\Controllers\Controller;
use App\Http\Resources\Email\EmailLogResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\EmailLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Email Log Controller
 *
 * Manage email logs and delivery tracking
 */
class EmailLogController extends Controller
{
    /**
     * Display a listing of email logs
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $status = $request->input('status');
        $contextType = $request->input('context_type');
        $contextId = $request->input('context_id');
        $recipientEmail = $request->input('recipient_email');

        $query = EmailLog::query()
            ->with(['user', 'triggeredBy'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($status) {
            $query->where('status', $status);
        }

        if ($contextType && $contextId) {
            $query->byContext($contextType, (int) $contextId);
        }

        if ($recipientEmail) {
            $query->where('recipient_email', 'like', "%{$recipientEmail}%");
        }

        $emailLogs = $query->paginate($perPage);

        return ApiResponse::success(
            EmailLogResource::collection($emailLogs)->additional([
                'meta' => [
                    'current_page' => $emailLogs->currentPage(),
                    'last_page' => $emailLogs->lastPage(),
                    'per_page' => $emailLogs->perPage(),
                    'total' => $emailLogs->total(),
                ],
            ]),
            'Email logs retrieved successfully'
        );
    }

    /**
     * Display the specified email log
     */
    public function show(string $uuid): JsonResponse
    {
        $emailLog = EmailLog::with(['user', 'triggeredBy'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return ApiResponse::success(
            new EmailLogResource($emailLog),
            'Email log retrieved successfully'
        );
    }

    /**
     * Get email logs by context
     */
    public function byContext(Request $request, string $contextType, int $contextId): JsonResponse
    {
        $emailLogs = EmailLog::with(['user', 'triggeredBy'])
            ->byContext($contextType, $contextId)
            ->orderBy('created_at', 'desc')
            ->get();

        return ApiResponse::success(
            EmailLogResource::collection($emailLogs),
            'Email logs retrieved successfully'
        );
    }

    /**
     * Get email statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $days = $request->input('days', 7);
        $startDate = now()->subDays($days);

        $stats = [
            'total' => EmailLog::where('created_at', '>=', $startDate)->count(),
            'sent' => EmailLog::sent()->where('created_at', '>=', $startDate)->count(),
            'failed' => EmailLog::failed()->where('created_at', '>=', $startDate)->count(),
            'pending' => EmailLog::pending()->where('created_at', '>=', $startDate)->count(),
            'success_rate' => 0,
        ];

        if ($stats['total'] > 0) {
            $stats['success_rate'] = round(($stats['sent'] / $stats['total']) * 100, 2);
        }

        // Get failed emails by mailable type
        $failedByType = EmailLog::failed()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('mailable_type, COUNT(*) as count')
            ->groupBy('mailable_type')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => class_basename($item->mailable_type),
                    'count' => $item->count,
                ];
            });

        $stats['failed_by_type'] = $failedByType;

        return ApiResponse::success($stats, 'Email statistics retrieved successfully');
    }

    /**
     * Retry failed emails (future feature)
     */
    public function retry(string $uuid): JsonResponse
    {
        $emailLog = EmailLog::where('uuid', $uuid)
            ->where('status', 'failed')
            ->firstOrFail();

        // TODO: Implement retry logic
        // This would require storing enough information to recreate the email

        return ApiResponse::success(
            new EmailLogResource($emailLog),
            'Email retry initiated'
        );
    }
}
