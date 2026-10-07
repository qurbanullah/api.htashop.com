<?php

namespace App\Services\Bom;

use App\Enums\BomRequestStatus;
use App\Models\BomLine;
use App\Models\BomRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BomRequestService
{
    /**
     * Create a sourcing request together with its lines in one transaction, so
     * a partial write can never leave a request without its parts list.
     */
    public function create(array $data): BomRequest
    {
        return DB::transaction(function () use ($data): BomRequest {
            $request = BomRequest::create([
                'name' => data_get($data, 'name'),
                'email' => data_get($data, 'email'),
                'phone' => data_get($data, 'phone'),
                'company' => data_get($data, 'company'),
                'title' => data_get($data, 'title'),
                'notes' => data_get($data, 'notes'),
                'status' => BomRequestStatus::PENDING,
                'metadata' => data_get($data, 'metadata'),
            ]);

            $sortOrder = 0;
            foreach (data_get($data, 'lines', []) as $line) {
                $request->lines()->create([
                    'part_name' => data_get($line, 'part_name'),
                    'part_number' => data_get($line, 'part_number'),
                    'specification' => data_get($line, 'specification'),
                    'quantity' => (int) data_get($line, 'quantity', 1),
                    'unit' => data_get($line, 'unit'),
                    'target_unit_price' => data_get($line, 'target_unit_price'),
                    'source_url' => data_get($line, 'source_url'),
                    'notes' => data_get($line, 'notes'),
                    'sort_order' => $sortOrder++,
                ]);
            }

            return $request->load('lines');
        });
    }

    public function read(array $filters = []): LengthAwarePaginator
    {
        return BomRequest::query()
            ->with('lines')
            ->when(data_get($filters, 'status'), fn ($query, $status) => $query->where('status', $status))
            ->when(data_get($filters, 'search'), function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate((int) data_get($filters, 'per_page', 15));
    }

    public function show(string $uuid): ?BomRequest
    {
        return BomRequest::query()->with('lines')->where('uuid', $uuid)->first();
    }

    public function updateStatus(BomRequest $request, string $status): BomRequest
    {
        if (! in_array($status, BomRequestStatus::ALL, true)) {
            throw ValidationException::withMessages(['status' => 'Invalid BOM request status.']);
        }

        $request->update(['status' => $status]);

        return $request->fresh('lines');
    }

    /**
     * @return array<string, int>
     */
    public function getStatistics(): array
    {
        return [
            'total' => BomRequest::count(),
            'pending' => BomRequest::where('status', BomRequestStatus::PENDING)->count(),
            'in_review' => BomRequest::where('status', BomRequestStatus::IN_REVIEW)->count(),
            'quoted' => BomRequest::where('status', BomRequestStatus::QUOTED)->count(),
            'sourcing' => BomRequest::where('status', BomRequestStatus::SOURCING)->count(),
            'fulfilled' => BomRequest::where('status', BomRequestStatus::FULFILLED)->count(),
            'this_month' => BomRequest::whereMonth('created_at', now()->month)->count(),
            'total_lines' => BomLine::count(),
        ];
    }
}
