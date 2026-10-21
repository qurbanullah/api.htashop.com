<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Audit;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Services\Audit\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AuditController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAuditAccess($request);

        $audits = $this->filtered($request)
            ->with('user')
            ->latest('occurred_at')
            ->paginate((int) $request->input('per_page', 25));

        $audits->getCollection()->transform(function (Audit $audit): Audit {
            $audit->auditable_type_name ??= $audit->auditable_type
                ? class_basename($audit->auditable_type)
                : null;

            return $audit;
        });

        return response()->json($audits);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->authorizeAuditAccess($request);

        $audit = Audit::with('user')->findOrFail($id);
        $audit->auditable_type_name ??= $audit->auditable_type
            ? class_basename($audit->auditable_type)
            : null;

        return response()->json(['data' => $audit]);
    }

    public function adminStats(Request $request): JsonResponse
    {
        $this->authorizeAuditAccess($request);

        try {
            $filters = [];

            if ($request->filled('start_date')) {
                $filters['start_date'] = $request->input('start_date');
            }

            if ($request->filled('end_date')) {
                $filters['end_date'] = $request->input('end_date');
            }

            return response()->json([
                'success' => true,
                'data' => $this->auditService->getAdminStats($filters),
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $this->authorizeAuditAccess($request);

        $audits = $this->filtered($request)
            ->with('user')
            ->latest('occurred_at')
            ->paginate((int) $request->input('per_page', 25));

        $items = $audits->getCollection()
            ->transform(function (Audit $audit): Audit {
                $audit->auditable_type_name ??= $audit->auditable_type
                    ? class_basename($audit->auditable_type)
                    : null;

                return $audit;
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $audits->currentPage(),
                'last_page' => $audits->lastPage(),
                'per_page' => $audits->perPage(),
                'total' => $audits->total(),
            ],
        ]);
    }

    public function exportCsv(Request $request)
    {
        $this->authorizeAuditAccess($request);

        $audits = $this->filtered($request)
            ->latest('occurred_at')
            ->limit(10000)
            ->get();

        $handle = fopen('php://temp', 'w+');

        if ($handle === false) {
            abort(500, 'Could not build the CSV export.');
        }

        fputcsv($handle, [
            'ID',
            'UUID',
            'Actor Type',
            'Actor ID',
            'Event',
            'Auditable Type',
            'Auditable ID',
            'Changed Columns',
            'Reason',
            'Description',
            'Request ID',
            'IP Hash',
            'User Agent',
            'Occurred At',
        ]);

        foreach ($audits as $audit) {
            fputcsv($handle, [
                $audit->id,
                $audit->uuid,
                $audit->actor_type,
                $audit->actor_id ?? $audit->user_id ?? 'System',
                $audit->event,
                $audit->auditable_type ? class_basename($audit->auditable_type) : '',
                $audit->auditable_id ?? '',
                implode(', ', $audit->changed_columns ?? []),
                $this->csvCell($audit->reason),
                $this->csvCell($audit->description),
                $audit->request_id,
                $audit->ip_hash,
                $this->csvCell($audit->user_agent),
                $audit->occurred_at?->toIso8601String(),
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        $filename = 'audits_'.now()->format('Y-m-d_His').'.csv';

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function filtered(Request $request): Builder
    {
        return Audit::query()
            ->when($request->filled('actor_id'), function (Builder $query) use ($request): void {
                $actorId = $request->input('actor_id');
                $query->where(function (Builder $inner) use ($actorId): void {
                    $inner->where('actor_id', $actorId)->orWhere('user_id', $actorId);
                });
            })
            ->when($request->filled('actor_type'), fn (Builder $query) => $query->where('actor_type', $request->input('actor_type')))
            ->when($request->filled('event'), fn (Builder $query) => $query->where('event', $request->input('event')))
            ->when($request->filled('auditable_type'), fn (Builder $query) => $query->where('auditable_type', 'like', '%'.$request->input('auditable_type').'%'))
            ->when($request->filled('auditable_id'), fn (Builder $query) => $query->where('auditable_id', $request->input('auditable_id')))
            ->when($request->filled('tenant_id'), fn (Builder $query) => $query->where('tenant_id', $request->input('tenant_id')))
            ->when($request->filled('request_id'), fn (Builder $query) => $query->where('request_id', $request->input('request_id')))
            ->when($request->filled('ip_hash'), fn (Builder $query) => $query->where('ip_hash', $request->input('ip_hash')))
            ->when($request->filled('start_date'), fn (Builder $query) => $query->where('occurred_at', '>=', $request->input('start_date')))
            ->when($request->filled('end_date'), fn (Builder $query) => $query->where('occurred_at', '<=', $request->input('end_date')));
    }

    private function authorizeAuditAccess(Request $request): void
    {
        $user = $request->user();

        if (! $user || ! $user->hasAnyRole(['admin', 'super-admin'])) {
            abort(403);
        }
    }

    private function csvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        // Neutralise spreadsheet formula injection.
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }
}
