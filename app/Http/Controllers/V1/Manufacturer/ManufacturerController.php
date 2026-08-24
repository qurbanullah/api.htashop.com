<?php

namespace App\Http\Controllers\V1\Manufacturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Manufacturer\ManufacturerStoreRequest;
use App\Http\Requests\V1\Manufacturer\ManufacturerUpdateRequest;
use App\Http\Resources\V1\Manufacturer\ManufacturerResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Manufacturer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ManufacturerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user && $user->hasRole(['super-admin', 'admin']);

        $manufacturers = Manufacturer::query()
            ->when($isAdmin, fn ($query) => $query->adminView($request->string('approval_status')->toString() ?: null))
            ->when(! $isAdmin, fn ($query) => $query->vendorVisible($user))
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%' . $request->string('search') . '%'))
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('is_active', true))
            ->orderByDesc('is_approved')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(ManufacturerResource::collection($manufacturers), 'Manufacturers retrieved successfully');
    }

    public function store(ManufacturerStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->resolveSlug(data_get($data, 'slug'), $data['name']);

        Manufacturer::applyOrigin($data, $request->user());

        $manufacturer = Manufacturer::create($data);

        return ApiResponse::success(new ManufacturerResource($manufacturer), 'Manufacturer created successfully', 201);
    }

    public function approve(Request $request, string $uuid): JsonResponse
    {
        $manufacturer = Manufacturer::where('uuid', $uuid)->firstOrFail();
        $manufacturer->approve();

        return ApiResponse::success(new ManufacturerResource($manufacturer->fresh()), 'Manufacturer approved successfully');
    }

    public function reject(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $manufacturer = Manufacturer::where('uuid', $uuid)->firstOrFail();
        $manufacturer->reject($data['reason']);

        return ApiResponse::success(new ManufacturerResource($manufacturer->fresh()), 'Manufacturer rejected successfully');
    }

    public function show(string $uuid): JsonResponse
    {
        $manufacturer = Manufacturer::where('uuid', $uuid)->firstOrFail();

        return ApiResponse::success(new ManufacturerResource($manufacturer), 'Manufacturer retrieved successfully');
    }

    public function update(ManufacturerUpdateRequest $request, string $uuid): JsonResponse
    {
        $manufacturer = Manufacturer::where('uuid', $uuid)->firstOrFail();
        $data = $request->validated();

        if (array_key_exists('slug', $data) || array_key_exists('name', $data)) {
            $data['slug'] = $this->resolveSlug(
                data_get($data, 'slug', $manufacturer->slug),
                data_get($data, 'name', $manufacturer->name),
                $manufacturer->id,
            );
        }

        $manufacturer->update($data);

        return ApiResponse::success(new ManufacturerResource($manufacturer), 'Manufacturer updated successfully');
    }

    public function destroy(string $uuid): JsonResponse
    {
        $manufacturer = Manufacturer::where('uuid', $uuid)->firstOrFail();
        $manufacturer->delete();

        return ApiResponse::success(null, 'Manufacturer deleted successfully');
    }

    private function resolveSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $candidate = Str::slug($slug ?: $name);
        $base = $candidate;
        $suffix = 1;

        while (Manufacturer::withTrashed()
            ->where('slug', $candidate)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
