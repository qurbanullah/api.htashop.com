<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Newsletter;

use App\Http\Controllers\Controller;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Subscribe;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Admin-only newsletter audience stats for the Post screen.
 */
class NewsletterAdminController extends Controller
{
    public function subscribers(): JsonResponse
    {
        try {
            $scope = TenantContext::adminScope();

            $query = Subscribe::query()->where('type', 'newsletter');

            if ($scope === 0) {
                $query->whereRaw('1 = 0');
            } elseif ($scope !== null) {
                $query->where('subscribes.tenant_id', $scope);
            }

            $total = (clone $query)->count();
            $active = (clone $query)->where('is_subscribed', true)->count();
            $unsubscribed = $total - $active;

            return ApiResponse::success([
                'total' => $total,
                'active' => $active,
                'unsubscribed' => $unsubscribed,
            ], 'Newsletter subscriber stats retrieved successfully');
        } catch (\Throwable $throwable) {
            Log::error('Failed to retrieve newsletter subscriber stats', [
                'error' => $throwable->getMessage(),
            ]);

            return ApiResponse::error('Failed to retrieve newsletter subscriber stats', null, 500);
        }
    }
}
