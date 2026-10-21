<?php

declare(strict_types=1);

namespace App\Jobs\Analytics;

use App\Services\Analytics\AnalyticsService;
use App\Support\Analytics\EventEnvelope;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Validated analytics event, processed off the request path.
 *
 * The job only writes to Redis (counters/dedupe/optional stream). MariaDB is
 * updated later by `analytics:flush`.
 */
final class RecordEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 10;

    /** @var array<int, int> */
    public array $backoff = [5, 15, 30];

    public function __construct(
        public array $event,
    ) {
        $this->onQueue((string) config('analytics.queue', 'analytics'));
    }

    public function handle(AnalyticsService $analytics): void
    {
        $analytics->record(EventEnvelope::fromArray($this->event));
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('analytics.event.failed', [
            'event_name' => $this->event['event_name'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
