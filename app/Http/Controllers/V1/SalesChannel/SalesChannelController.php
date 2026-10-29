<?php

namespace App\Http\Controllers\V1\SalesChannel;

use App\Enums\SalesChannel as SalesChannelCode;
use App\Http\Controllers\Controller;
use App\Http\Responses\V1\ApiResponse;
use App\Models\SalesChannel;
use Illuminate\Http\JsonResponse;

class SalesChannelController extends Controller
{
    /**
     * List the channels a product can be published to.
     */
    public function index(): JsonResponse
    {
        $channels = SalesChannel::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['code', 'name'])
            ->map(fn (SalesChannel $channel) => [
                'code' => $channel->code,
                'name' => $channel->name,
                'description' => SalesChannelCode::description($channel->code),
            ])
            ->values();

        return ApiResponse::success($channels, 'Sales channels retrieved successfully');
    }
}
