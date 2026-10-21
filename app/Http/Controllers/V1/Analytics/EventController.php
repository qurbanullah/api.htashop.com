<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Analytics;

use App\Enums\Analytics\EventName;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AssignRequestId;
use App\Http\Responses\V1\ApiResponse;
use App\Jobs\Analytics\RecordEvent;
use App\Support\Analytics\ClientContext;
use App\Support\Analytics\EventSanitizer;
use App\Support\Observability\IpHasher;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Consent-gated first-party event beacon.
 *
 * The endpoint accepts a batch and returns 202 as soon as the events are
 * validated and queued. Analytics must never slow down or break a storefront
 * request; the queue is the only failure boundary.
 */
class EventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! config('analytics.beacon.enabled', true)) {
            return ApiResponse::success(['accepted' => 0, 'dropped' => 0], 'Analytics collection is disabled', 202);
        }

        $maxBytes = max(1024, (int) config('analytics.beacon.max_payload_bytes', 65536));

        if (strlen((string) $request->getContent()) > $maxBytes) {
            return ApiResponse::error('Analytics payload is too large.', null, 413);
        }

        $maxBatch = max(1, (int) config('analytics.beacon.max_batch', 20));

        $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:'.$maxBatch],
            'events.*.event_name' => ['required', 'string', Rule::in(EventName::values())],
            'events.*.occurred_at' => ['nullable', 'date'],
            'events.*.session_id' => ['nullable', 'string', 'max:120'],
            'events.*.anonymous_id' => ['nullable', 'string', 'max:120'],
            'events.*.source' => ['nullable', 'string', Rule::in(['web', 'api'])],
            'events.*.context' => ['nullable', 'array'],
            'events.*.context.consent.analytics' => ['nullable', 'boolean'],
        ]);

        // Use the raw input, not `$validated`: Laravel returns only the keys
        // that have rules, which would strip context.product, context.order and
        // every other analytical dimension.
        $events = $request->input('events', []);
        $events = is_array($events) ? $events : [];

        $requestId = AssignRequestId::fromRequest($request);
        $tenantId = TenantContext::publicTenantId($request);
        $userId = $request->user('api')?->id;
        $ipHash = IpHasher::hash($request->ip());
        $userAgent = Str::limit((string) $request->userAgent(), 512, '');
        $client = ClientContext::fromClientAndUserAgent(null, $userAgent);

        $accepted = 0;
        $dropped = 0;

        foreach ($events as $event) {
            if (! is_array($event)) {
                $dropped++;

                continue;
            }

            $eventName = EventName::tryFrom((string) $event['event_name']);

            if ($eventName === null) {
                $dropped++;

                continue;
            }

            // Money events are server-authoritative. A browser must never be
            // able to fabricate revenue.
            if ($eventName->isServerOnly()) {
                $dropped++;

                continue;
            }

            $context = is_array($event['context'] ?? null) ? $event['context'] : [];
            $context = EventSanitizer::context($context);
            $consent = (bool) data_get($context, 'consent.analytics', false);

            // The public beacon only speaks for the browser. A client cannot
            // claim `source=api` to bypass the consent gate, and it cannot
            // backdate events into an already-closed rollup day.
            $source = 'web';

            if (! $consent) {
                $dropped++;

                continue;
            }

            $occurredAt = now();

            if (isset($event['occurred_at']) && is_string($event['occurred_at']) && $event['occurred_at'] !== '') {
                try {
                    $occurredAt = Carbon::parse($event['occurred_at']);
                } catch (\Throwable) {
                    $dropped++;

                    continue;
                }
            }

            if ($occurredAt->lt(now()->subDays(7)) || $occurredAt->gt(now()->addMinutes(5))) {
                $dropped++;

                continue;
            }

            $context['device'] = [
                'type' => $client->deviceType,
                'browser' => $client->browser,
                'os' => $client->platform,
            ];
            $context['consent'] = ['analytics' => $consent];

            RecordEvent::dispatch([
                'event_name' => $eventName->value,
                'schema_version' => 1,
                'occurred_at' => $occurredAt->toIso8601String(),
                'source' => $client->isBot ? 'bot' : $source,
                'session_id' => $event['session_id'] ?? null,
                'anonymous_id' => $event['anonymous_id'] ?? null,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'request_id' => $requestId,
                'ip_hash' => $ipHash,
                'user_agent' => $userAgent,
                'bot' => $client->isBot,
                'context' => $context,
            ]);

            $accepted++;
        }

        return ApiResponse::success(
            ['accepted' => $accepted, 'dropped' => $dropped],
            'Analytics events accepted',
            202,
        );
    }
}
