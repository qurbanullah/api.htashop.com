<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\Audit;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single writer for the audit hot mirror and the audit log channel.
 *
 * The MariaDB row is the durable record. The log line is best-effort and only
 * exists so Alloy/Loki can search it; a Loki outage must never lose the audit
 * row or fail the business action.
 */
final class AuditLogger
{
    /**
     * Record one audit event.
     *
     * The legacy `$meta` shape is retained because existing actions already use
     * it (`old_values`, `new_values`, `description`, ...). New call sites should
     * add `reason`, `changed_columns` and `tenant_id` where they are known.
     */
    public function record(?User $actor, string $event, mixed $auditable = null, array $meta = []): Audit
    {
        $data = AuditEventData::fromLegacy($actor, $event, $auditable, $meta);

        /** @var Audit $audit */
        $audit = Audit::query()->create($data->toAttributes());

        $this->emit($data, $audit);

        return $audit;
    }

    /**
     * System/CLI action with no authenticated user.
     */
    public function recordSystem(string $event, mixed $auditable = null, array $meta = []): Audit
    {
        return $this->record(null, $event, $auditable, $meta);
    }

    private function emit(AuditEventData $data, Audit $audit): void
    {
        try {
            $context = $data->toLogContext();
            $context['uuid'] = $audit->uuid;

            Log::channel((string) config('observability.audit.channel', 'audit'))
                ->info('audit.event', $context);
        } catch (Throwable $exception) {
            // The row is already durable. Do not turn a log-shipping problem
            // into a business failure; surface it for the next health check.
            report($exception);
        }
    }
}
