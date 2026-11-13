<?php

namespace App\Actions\Quotes;

use App\Enums\QuoteResponseStatus;
use App\Enums\QuoteStatus;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Platform-wide quote analytics for the admin portal.
 *
 * Aggregation happens in PHP on purpose: quote volume is modest, and it keeps
 * the report portable between MariaDB and the SQLite test database.
 */
class BuildQuoteReportAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $days = 90): array
    {
        $since = $days > 0 ? now()->subDays($days)->startOfDay() : null;

        $quotes = QuoteRequest::query()
            ->when($since !== null, fn ($query) => $query->where('created_at', '>=', $since))
            ->with(['latestResponse'])
            ->get($this->columns());

        $statusCounts = $this->statusCounts($quotes);
        $accepted = $statusCounts[QuoteStatus::ACCEPTED] ?? 0;
        $declined = $statusCounts[QuoteStatus::DECLINED] ?? 0;
        $expired = $statusCounts[QuoteStatus::EXPIRED] ?? 0;
        $decided = $accepted + $declined + $expired;

        return [
            'period_days' => $days,
            'generated_at' => now()->toISOString(),
            'kpis' => [
                'total' => $quotes->count(),
                'open' => $quotes->whereIn('status', QuoteStatus::OPEN)->count(),
                'overdue' => $quotes->filter(fn (QuoteRequest $q): bool => $q->isOverdue())->count(),
                'accepted' => $accepted,
                'declined' => $declined,
                'expired' => $expired,
                'cancelled' => $statusCounts[QuoteStatus::CANCELLED] ?? 0,
                // Win rate is measured against decided quotes only, so open
                // quotes do not dilute the percentage.
                'win_rate' => $decided > 0 ? round($accepted / $decided * 100, 1) : null,
                'avg_first_response_minutes' => $this->average(
                    $quotes
                        ->filter(fn (QuoteRequest $q): bool => $q->first_response_at !== null)
                        ->map(fn (QuoteRequest $q): int => max(0, (int) $q->created_at->diffInMinutes($q->first_response_at)))
                ),
                'avg_decision_minutes' => $this->average(
                    $quotes
                        ->filter(fn (QuoteRequest $q): bool => $q->decidedAt() !== null)
                        ->map(fn (QuoteRequest $q): int => max(0, (int) $q->created_at->diffInMinutes($q->decidedAt())))
                ),
                'quoted_value_by_currency' => $this->valueByCurrency($quotes),
                'accepted_value_by_currency' => $this->valueByCurrency($quotes, onlyAccepted: true),
                'currency' => $this->primaryCurrency($quotes),
            ],
            'by_status' => collect(QuoteStatus::ALL)
                ->map(fn (string $status): array => [
                    'status' => $status,
                    'label' => QuoteStatus::label($status),
                    'count' => $statusCounts[$status] ?? 0,
                ])
                ->all(),
            'by_priority' => $this->priorityBreakdown($quotes),
            'trend' => $this->trend(),
            'response_buckets' => $this->responseBuckets($quotes),
            'top_organizations' => $this->topOrganizations($quotes),
            'assignees' => $this->assigneePerformance($quotes),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function columns(): array
    {
        return [
            'id',
            'status',
            'priority',
            'assigned_to',
            'organization_id',
            'tenant_id',
            'created_at',
            'first_response_at',
            'response_due_at',
            'accepted_at',
            'declined_at',
        ];
    }

    /**
     * @param  Collection<int, QuoteRequest>  $quotes
     * @return array<string, int>
     */
    private function statusCounts(Collection $quotes): array
    {
        $counts = array_fill_keys(QuoteStatus::ALL, 0);

        foreach ($quotes as $quote) {
            $counts[$quote->status] = ($counts[$quote->status] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param  Collection<int, int>  $values
     */
    private function average(Collection $values): ?int
    {
        $filtered = $values->filter(fn (int $value): bool => $value >= 0)->values();

        return $filtered->isEmpty() ? null : (int) round($filtered->avg());
    }

    /**
     * @param  Collection<int, QuoteRequest>  $quotes
     * @return array<string, float>
     */
    private function valueByCurrency(Collection $quotes, bool $onlyAccepted = false): array
    {
        $totals = [];

        foreach ($quotes as $quote) {
            if ($onlyAccepted && $quote->status !== QuoteStatus::ACCEPTED) {
                continue;
            }

            $response = $quote->latestResponse;

            if ($response === null || $response->total_amount === null) {
                continue;
            }

            // Drafts and superseded revisions never represented an offer.
            if (! in_array($response->status, [
                QuoteResponseStatus::SENT,
                QuoteResponseStatus::VIEWED,
                QuoteResponseStatus::ACCEPTED,
                QuoteResponseStatus::DECLINED,
                QuoteResponseStatus::EXPIRED,
            ], true)) {
                continue;
            }

            $currency = $response->currency ?: 'PKR';
            $totals[$currency] = round(($totals[$currency] ?? 0) + (float) $response->total_amount, 2);
        }

        return $totals;
    }

    /**
     * @param  Collection<int, QuoteRequest>  $quotes
     */
    private function primaryCurrency(Collection $quotes): string
    {
        $currencies = $this->valueByCurrency($quotes);

        if ($currencies === []) {
            return 'PKR';
        }

        arsort($currencies);

        return (string) array_key_first($currencies);
    }

    /**
     * @param  Collection<int, QuoteRequest>  $quotes
     * @return array<int, array{priority: string, label: string, count: int}>
     */
    private function priorityBreakdown(Collection $quotes): array
    {
        $labels = ['urgent' => 'Urgent', 'high' => 'High', 'normal' => 'Normal', 'low' => 'Low', 'unset' => 'Unset'];
        $counts = array_fill_keys(array_keys($labels), 0);

        foreach ($quotes as $quote) {
            $key = $quote->priority ?: 'unset';
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return collect($counts)
            ->map(fn (int $count, string $priority): array => [
                'priority' => $priority,
                'label' => $labels[$priority] ?? ucfirst($priority),
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    /**
     * Received / accepted / declined per month for the last 12 months.
     *
     * @return array<int, array{month: string, label: string, received: int, accepted: int, declined: int}>
     */
    private function trend(): array
    {
        $start = now()->startOfMonth()->subMonthsNoOverflow(11);

        $rows = QuoteRequest::query()
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'accepted_at', 'declined_at']);

        return collect(range(0, 11))
            ->map(function (int $offset) use ($start, $rows): array {
                /** @var Carbon $month */
                $month = $start->copy()->addMonthsNoOverflow($offset);
                $key = $month->format('Y-m');

                return [
                    'month' => $key,
                    'label' => $month->format('M Y'),
                    'received' => $rows->filter(fn (QuoteRequest $q): bool => $q->created_at?->format('Y-m') === $key)->count(),
                    'accepted' => $rows->filter(fn (QuoteRequest $q): bool => $q->accepted_at?->format('Y-m') === $key)->count(),
                    'declined' => $rows->filter(fn (QuoteRequest $q): bool => $q->declined_at?->format('Y-m') === $key)->count(),
                ];
            })
            ->all();
    }

    /**
     * How quickly the team sends the first response.
     *
     * @param  Collection<int, QuoteRequest>  $quotes
     * @return array<int, array{key: string, label: string, value: int}>
     */
    private function responseBuckets(Collection $quotes): array
    {
        $buckets = [
            'under_1h' => 0,
            '1h_4h' => 0,
            '4h_1d' => 0,
            '1d_3d' => 0,
            'over_3d' => 0,
            'no_response' => 0,
        ];

        foreach ($quotes as $quote) {
            if ($quote->first_response_at === null) {
                $buckets['no_response']++;

                continue;
            }

            $minutes = max(0, (int) $quote->created_at->diffInMinutes($quote->first_response_at));

            match (true) {
                $minutes <= 60 => $buckets['under_1h']++,
                $minutes <= 240 => $buckets['1h_4h']++,
                $minutes <= 1440 => $buckets['4h_1d']++,
                $minutes <= 4320 => $buckets['1d_3d']++,
                default => $buckets['over_3d']++,
            };
        }

        $labels = [
            'under_1h' => '< 1 hour',
            '1h_4h' => '1–4 hours',
            '4h_1d' => '4–24 hours',
            '1d_3d' => '1–3 days',
            'over_3d' => '> 3 days',
            'no_response' => 'Awaiting response',
        ];

        return collect($buckets)
            ->map(fn (int $value, string $key): array => [
                'key' => $key,
                'label' => $labels[$key],
                'value' => $value,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, QuoteRequest>  $quotes
     * @return array<int, array<string, mixed>>
     */
    private function topOrganizations(Collection $quotes): array
    {
        $groups = $quotes->filter(fn (QuoteRequest $q): bool => $q->organization_id !== null || $q->tenant_id !== null)
            ->groupBy(fn (QuoteRequest $q): string => $q->organization_id !== null
                ? 'org-'.$q->organization_id
                : 'tenant-'.$q->tenant_id);

        $organizationIds = $groups->keys()
            ->filter(fn (string $key): bool => str_starts_with($key, 'org-'))
            ->map(fn (string $key): int => (int) substr($key, 4));

        $tenantIds = $groups->keys()
            ->filter(fn (string $key): bool => str_starts_with($key, 'tenant-'))
            ->map(fn (string $key): int => (int) substr($key, 7));

        $organizationNames = Organization::query()->whereIn('id', $organizationIds)->pluck('name', 'id');
        $tenantNames = Tenant::query()->whereIn('id', $tenantIds)->pluck('name', 'id');

        return $groups
            ->map(function (Collection $group, string $key) use ($organizationNames, $tenantNames): array {
                $isOrganization = str_starts_with($key, 'org-');
                $id = (int) substr($key, $isOrganization ? 4 : 7);
                $accepted = $group->where('status', QuoteStatus::ACCEPTED)->count();
                $decided = $group->whereIn('status', [
                    QuoteStatus::ACCEPTED,
                    QuoteStatus::DECLINED,
                    QuoteStatus::EXPIRED,
                ])->count();

                return [
                    'id' => $id,
                    'type' => $isOrganization ? 'organization' : 'tenant',
                    'name' => $isOrganization
                        ? ($organizationNames[$id] ?? 'Organization #'.$id)
                        : ($tenantNames[$id] ?? 'Tenant #'.$id),
                    'total' => $group->count(),
                    'accepted' => $accepted,
                    'win_rate' => $decided > 0 ? round($accepted / $decided * 100, 1) : null,
                    'accepted_value_by_currency' => $this->valueByCurrency(
                        $group->where('status', QuoteStatus::ACCEPTED)
                    ),
                ];
            })
            ->sortByDesc('total')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, QuoteRequest>  $quotes
     * @return array<int, array<string, mixed>>
     */
    private function assigneePerformance(Collection $quotes): array
    {
        $groups = $quotes->whereNotNull('assigned_to')->groupBy('assigned_to');

        if ($groups->isEmpty()) {
            return [];
        }

        $names = User::query()->whereIn('id', $groups->keys())->pluck('name', 'id');

        return $groups
            ->map(function (Collection $group, int|string $userId) use ($names): array {
                $id = (int) $userId;

                return [
                    'id' => $id,
                    'name' => $names[$id] ?? 'User #'.$id,
                    'assigned' => $group->count(),
                    'accepted' => $group->where('status', QuoteStatus::ACCEPTED)->count(),
                    'avg_first_response_minutes' => $this->average(
                        $group
                            ->filter(fn (QuoteRequest $q): bool => $q->first_response_at !== null)
                            ->map(fn (QuoteRequest $q): int => max(0, (int) $q->created_at->diffInMinutes($q->first_response_at)))
                    ),
                ];
            })
            ->sortByDesc('assigned')
            ->take(8)
            ->values()
            ->all();
    }
}
