<?php

namespace App\Actions\Punchout;

use App\Models\PunchoutSession;
use Illuminate\Pagination\LengthAwarePaginator;

class PunchoutSessionReadAction
{
    public function handle(array $filters = []): LengthAwarePaginator
    {
        return PunchoutSession::query()
            ->with('tenant')
            ->when(data_get($filters, 'tenant_id'), fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when(data_get($filters, 'tenant_uuid'), function ($query, $tenantUuid) {
                $query->whereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('uuid', $tenantUuid));
            })
            ->when(data_get($filters, 'status'), fn ($query, $status) => $query->where('status', $status))
            ->when(data_get($filters, 'protocol'), fn ($query, $protocol) => $query->where('protocol', $protocol))
            ->when(data_get($filters, 'search'), function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('uuid', 'like', '%' . $search . '%')
                        ->orWhere('buyer_cookie', 'like', '%' . $search . '%')
                        ->orWhere('return_url', 'like', '%' . $search . '%')
                        ->orWhereHas('tenant', function ($tenantQuery) use ($search) {
                            $tenantQuery->where('uuid', 'like', '%' . $search . '%')
                                ->orWhere('slug', 'like', '%' . $search . '%')
                                ->orWhere('name', 'like', '%' . $search . '%');
                        });
                });
            })
            ->orderByDesc('created_at')
            ->paginate((int) data_get($filters, 'per_page', 15));
    }
}
