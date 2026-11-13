<?php

namespace App\Actions\Quotes;

use App\Enums\QuoteStatus;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class GetQuoteRequestsAction
{
    /**
     * Paginated, scoped quote list with the filters used by the manage UI.
     */
    public function execute(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->with([
                'assignedTo:id,name,email',
                'latestResponse',
                'quotable',
                'tenant:id,name',
                'organization:id,name',
            ])
            ->orderBy(...$this->order($filters))
            ->paginate(max(1, $perPage));
    }

    /**
     * Status counts for the list tabs and KPI strip.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $rows = QuoteRequest::query()
            ->accessibleBy($this->user())
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $counts = array_fill_keys(QuoteStatus::ALL, 0);

        foreach ($rows as $status => $total) {
            $counts[(string) $status] = (int) $total;
        }

        $counts['all'] = array_sum($counts);

        return $counts;
    }

    /**
     * @return array{avg_first_response_minutes: int|null, open: int, overdue: int}
     */
    public function stats(): array
    {
        $query = QuoteRequest::query()->accessibleBy($this->user());

        $durations = (clone $query)
            ->whereNotNull('first_response_at')
            ->latest('id')
            ->limit(500)
            ->get(['created_at', 'first_response_at'])
            ->map(fn (QuoteRequest $quote): int => (int) $quote->created_at?->diffInMinutes($quote->first_response_at))
            ->filter()
            ->values();

        $overdue = (clone $query)
            ->whereIn('status', QuoteStatus::OPEN)
            ->whereNotNull('response_due_at')
            ->where('response_due_at', '<', now())
            ->count();

        return [
            'avg_first_response_minutes' => $durations->isEmpty() ? null : (int) round($durations->avg()),
            'open' => (clone $query)->whereIn('status', QuoteStatus::OPEN)->count(),
            'overdue' => $overdue,
        ];
    }

    private function baseQuery(array $filters): Builder
    {
        $query = QuoteRequest::query()->accessibleBy($this->user());

        if ($status = data_get($filters, 'status')) {
            $query->where('status', $status);
        }

        if ($priority = data_get($filters, 'priority')) {
            $query->where('priority', $priority);
        }

        // Admin-only breadth: the scope above already limits merchants to their
        // own organizations, so accepting these from any caller is safe.
        if ($tenantId = data_get($filters, 'tenant_id')) {
            $query->where('tenant_id', (int) $tenantId);
        }

        if ($organizationId = data_get($filters, 'organization_id')) {
            $query->where('organization_id', (int) $organizationId);
        }

        $assigned = data_get($filters, 'assigned_to');

        if ($assigned === 'me') {
            $query->where('assigned_to', $this->user()->id);
        } elseif ($assigned === 'unassigned') {
            $query->whereNull('assigned_to');
        } elseif ($assigned) {
            $query->where('assigned_to', (int) $assigned);
        }

        if ($search = data_get($filters, 'search')) {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('quote_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('organization', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('requirements->product_name', 'like', "%{$search}%")
                    ->orWhere('requirements->sku', 'like', "%{$search}%")
                    ->orWhere('requirements->part_number', 'like', "%{$search}%");
            });
        }

        if ($from = data_get($filters, 'date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = data_get($filters, 'date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        if ($days = data_get($filters, 'expiring_within_days')) {
            $query->whereHas('latestResponse', function (Builder $response) use ($days): void {
                $response->whereNotNull('expires_at')
                    ->whereBetween('expires_at', [now(), now()->addDays((int) $days)]);
            });
        }

        return $query;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function order(array $filters): array
    {
        return match (data_get($filters, 'sort')) {
            'oldest' => ['created_at', 'asc'],
            'oldest_unanswered' => ['created_at', 'asc'],
            default => ['created_at', 'desc'],
        };
    }

    private function user(): User
    {
        return auth()->user();
    }
}
