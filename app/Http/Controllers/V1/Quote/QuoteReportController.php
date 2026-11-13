<?php

namespace App\Http\Controllers\V1\Quote;

use App\Actions\Quotes\BuildQuoteReportAction;
use App\Http\Controllers\Controller;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin-only quote reporting.
 */
class QuoteReportController extends Controller
{
    public function __construct(
        protected BuildQuoteReportAction $report,
    ) {}

    public function report(Request $request): JsonResponse
    {
        $data = $request->validate([
            'days' => ['nullable', 'integer', 'in:0,30,90,180,365'],
        ]);

        return ApiResponse::success(
            $this->report->execute((int) ($data['days'] ?? 90)),
            'Quote report generated successfully',
        );
    }

    /**
     * Organizations and tenants that actually have quotes, for admin filters.
     */
    public function accounts(): JsonResponse
    {
        $organizationIds = QuoteRequest::query()
            ->whereNotNull('organization_id')
            ->distinct()
            ->pluck('organization_id');

        $tenantIds = QuoteRequest::query()
            ->whereNotNull('tenant_id')
            ->distinct()
            ->pluck('tenant_id');

        return ApiResponse::success([
            'organizations' => Organization::query()
                ->whereIn('id', $organizationIds)
                ->orderBy('name')
                ->get(['id', 'name']),
            'tenants' => Tenant::query()
                ->whereIn('id', $tenantIds)
                ->orderBy('name')
                ->get(['id', 'name']),
        ], 'Quote accounts retrieved successfully');
    }
}
