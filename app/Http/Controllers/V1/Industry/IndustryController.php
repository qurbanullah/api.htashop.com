<?php

namespace App\Http\Controllers\V1\Industry;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Industry\IndustryStoreRequest;
use App\Http\Requests\V1\Industry\IndustryUpdateRequest;
use App\Http\Resources\V1\Industry\IndustryResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Industry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IndustryController extends Controller
{
    /**
     * List industries. Public callers get the active list; admin callers can
     * include inactive rows and usage counts.
     */
    public function index(Request $request): JsonResponse
    {
        $industries = Industry::query()
            ->with('parent:id,code,name')
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->active())
            ->when($request->boolean('with_counts'), fn ($query) => $query->withCount('quoteRequests'))
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            IndustryResource::collection($industries),
            'Industries retrieved successfully',
        );
    }

    public function store(IndustryStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $industry = Industry::create([
            'name' => $data['name'],
            'code' => $this->resolveCode($data['code'] ?? null, $data['name']),
            'parent_id' => $data['parent_id'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return ApiResponse::success(
            new IndustryResource($industry->load('parent')),
            'Industry created successfully',
            201,
        );
    }

    public function update(IndustryUpdateRequest $request, Industry $industry): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('code', $data) || array_key_exists('name', $data)) {
            $data['code'] = $this->resolveCode(
                $data['code'] ?? $industry->code,
                $data['name'] ?? $industry->name,
                $industry->id,
            );
        }

        $industry->update($data);

        return ApiResponse::success(
            new IndustryResource($industry->refresh()->load('parent')),
            'Industry updated successfully',
        );
    }

    public function destroy(Industry $industry): JsonResponse
    {
        $industry->delete();

        return ApiResponse::success(null, 'Industry deleted successfully');
    }

    /**
     * Codes are stable public identifiers, so collisions get a numeric suffix
     * rather than silently overwriting the existing taxonomy.
     */
    private function resolveCode(?string $code, string $name, ?int $ignoreId = null): string
    {
        $candidate = Str::slug($code ?: $name);
        $candidate = $candidate !== '' ? $candidate : 'industry';
        $base = $candidate;
        $suffix = 1;

        while (Industry::query()
            ->where('code', $candidate)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
