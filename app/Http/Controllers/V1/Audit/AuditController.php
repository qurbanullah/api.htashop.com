<?php

namespace App\Http\Controllers\V1\Audit;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Services\Audit\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class AuditController extends Controller
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        // Only admin/super-admin allowed
        if (!$user || !$user->hasAnyRole(['admin', 'super-admin'])) {
            abort(403);
        }

        $q = Audit::with('user');

        if ($request->filled('actor_id')) {
            $q->where('user_id', $request->input('actor_id'));
        }
        if ($request->filled('event')) {
            $q->where('event', $request->input('event'));
        }
        if ($request->filled('auditable_type')) {
            $q->where('auditable_type', $request->input('auditable_type'));
        }
        if ($request->filled('auditable_id')) {
            $q->where('auditable_id', $request->input('auditable_id'));
        }

        $perPage = intval($request->input('per_page', 25));

        $audits = $q->latest()->paginate($perPage);

        // add a friendly auditable type name for UI
        $audits->getCollection()->transform(function ($a) {
            $a->auditable_type_name = $a->auditable_type ? class_basename($a->auditable_type) : null;
            return $a;
        });

        return response()->json($audits);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['admin', 'super-admin'])) {
            abort(403);
        }

        $audit = Audit::with('user')->findOrFail($id);
        $audit->auditable_type_name = $audit->auditable_type ? class_basename($audit->auditable_type) : null;

        return response()->json(['data' => $audit]);
    }

    /**
     * Get admin audit statistics and analytics.
     */
    public function adminStats(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['admin', 'super-admin'])) {
            abort(403);
        }

        try {
            $filters = [];
            if ($request->filled('start_date')) {
                $filters['start_date'] = $request->input('start_date');
            }
            if ($request->filled('end_date')) {
                $filters['end_date'] = $request->input('end_date');
            }

            $stats = $this->auditService->getAdminStats($filters);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get audits with enhanced filtering for admin.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['admin', 'super-admin'])) {
            abort(403);
        }

        $query = Audit::with('user');

        // Apply filters
        if ($request->filled('actor_id')) {
            $query->where('user_id', $request->input('actor_id'));
        }
        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }
        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', 'like', '%' . $request->input('auditable_type') . '%');
        }
        if ($request->filled('auditable_id')) {
            $query->where('auditable_id', $request->input('auditable_id'));
        }
        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->input('end_date'));
        }
        if ($request->filled('ip_address')) {
            $query->where('ip_address', $request->input('ip_address'));
        }

        $perPage = intval($request->input('per_page', 25));
        $audits = $query->latest()->paginate($perPage);

        // add auditable_type_name on each item for easier display in UI
        $items = $audits->getCollection()->transform(function ($a) {
            $a->auditable_type_name = $a->auditable_type ? class_basename($a->auditable_type) : null;
            return $a;
        })->values()->all();

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

    /**
     * Export audits to CSV.
     */
    public function exportCsv(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['admin', 'super-admin'])) {
            abort(403);
        }

        $query = Audit::query();

        // Apply same filters as adminIndex
        if ($request->filled('actor_id')) {
            $query->where('user_id', $request->input('actor_id'));
        }
        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }
        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', 'like', '%' . $request->input('auditable_type') . '%');
        }
        if ($request->filled('auditable_id')) {
            $query->where('auditable_id', $request->input('auditable_id'));
        }
        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->input('end_date'));
        }
        if ($request->filled('ip_address')) {
            $query->where('ip_address', $request->input('ip_address'));
        }

        $audits = $query->latest()->limit(10000)->get();

        $csv = "ID,Actor ID,Event,Auditable Type,Auditable ID,Description,IP Address,User Agent,Created At\n";

        foreach ($audits as $audit) {
            $csv .= implode(',', [
                $audit->id,
                $audit->user_id ?? 'System',
                $audit->event ?? '',
                $audit->auditable_type ? class_basename($audit->auditable_type) : '',
                $audit->auditable_id ?? '',
                '"' . str_replace('"', '""', $audit->description ?? '') . '"',
                $audit->ip_address ?? '',
                '"' . str_replace('"', '""', $audit->user_agent ?? '') . '"',
                $audit->created_at,
            ]) . "\n";
        }

        $filename = 'audits_' . now()->format('Y-m-d_His') . '.csv';

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
