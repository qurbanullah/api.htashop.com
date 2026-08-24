<?php

namespace App\Http\Controllers\V1\Brand;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Brand\BrandStoreRequest;
use App\Http\Requests\V1\Brand\BrandUpdateRequest;
use App\Http\Resources\V1\Brand\BrandResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $manufacturerId = $request->integer('manufacturer_id');
        $user = $request->user();
        $isAdmin = $user && $user->hasRole(['super-admin', 'admin']);

        $brands = Brand::query()
            ->when($manufacturerId, fn ($query) => $query->where('manufacturer_id', $manufacturerId))
            ->when($isAdmin, fn ($query) => $query->adminView($request->string('approval_status')->toString() ?: null))
            ->when(! $isAdmin, fn ($query) => $query->vendorVisible($user))
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%' . $request->string('search') . '%'))
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('is_active', true))
            ->with('manufacturer')
            ->orderByDesc('is_approved')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(BrandResource::collection($brands), 'Brands retrieved successfully');
    }

    public function store(BrandStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->resolveSlug(data_get($data, 'slug'), $data['name']);

        Brand::applyOrigin($data, $request->user());

        $brand = Brand::create($data);

        return ApiResponse::success(new BrandResource($brand->load('manufacturer')), 'Brand created successfully', 201);
    }

    public function approve(Request $request, string $uuid): JsonResponse
    {
        $brand = Brand::where('uuid', $uuid)->firstOrFail();
        $brand->approve();

        return ApiResponse::success(new BrandResource($brand->fresh('manufacturer')), 'Brand approved successfully');
    }

    public function reject(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $brand = Brand::where('uuid', $uuid)->firstOrFail();
        $brand->reject($data['reason']);

        return ApiResponse::success(new BrandResource($brand->fresh('manufacturer')), 'Brand rejected successfully');
    }

    public function show(string $uuid): JsonResponse
    {
        $brand = Brand::where('uuid', $uuid)->with('manufacturer')->firstOrFail();

        return ApiResponse::success(new BrandResource($brand), 'Brand retrieved successfully');
    }

    public function update(BrandUpdateRequest $request, string $uuid): JsonResponse
    {
        $brand = Brand::where('uuid', $uuid)->firstOrFail();
        $data = $request->validated();

        if (array_key_exists('slug', $data) || array_key_exists('name', $data)) {
            $data['slug'] = $this->resolveSlug(
                data_get($data, 'slug', $brand->slug),
                data_get($data, 'name', $brand->name),
                $brand->id,
            );
        }

        $brand->update($data);

        return ApiResponse::success(new BrandResource($brand->load('manufacturer')), 'Brand updated successfully');
    }

    public function destroy(string $uuid): JsonResponse
    {
        $brand = Brand::where('uuid', $uuid)->firstOrFail();
        $brand->delete();

        return ApiResponse::success(null, 'Brand deleted successfully');
    }

    private function resolveSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $candidate = Str::slug($slug ?: $name);
        $base = $candidate;
        $suffix = 1;

        while (Brand::withTrashed()
            ->where('slug', $candidate)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
