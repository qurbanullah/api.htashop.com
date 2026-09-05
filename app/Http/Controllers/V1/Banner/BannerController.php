<?php

namespace App\Http\Controllers\V1\Banner;

use App\Enums\BannerPlacement;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Banner\BannerResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Banner;
use App\Services\Banner\BannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public banner endpoint — resolves active banners for a placement and context.
 */
class BannerController extends Controller
{
    public function __construct(
        protected BannerService $bannerService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $placement = $request->string('placement')->toString() ?: BannerPlacement::HOME;

        $banners = $this->bannerService->forContext(
            $placement,
            $request->integer('category_id') ?: null,
            $request->string('q')->toString(),
        );

        return ApiResponse::success(
            BannerResource::collection($banners),
            'Banners retrieved successfully',
        );
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $banners = Banner::query()
            ->when($request->filled('placement'), fn ($q) => $q->where('placement', $request->string('placement')))
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', '%' . $request->string('search') . '%'))
            ->withTrashed()
            ->orderBy('placement')
            ->orderBy('sort_order')
            ->get();

        return ApiResponse::success(BannerResource::collection($banners), 'Banners retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $banner = Banner::create($data);
        $this->bannerService->flushCache();

        return ApiResponse::success(new BannerResource($banner), 'Banner created successfully', 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $banner = Banner::where('uuid', $uuid)->withTrashed()->firstOrFail();

        return ApiResponse::success(new BannerResource($banner), 'Banner retrieved successfully');
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $banner = Banner::where('uuid', $uuid)->withTrashed()->firstOrFail();
        $data = $request->validate($this->rules());

        // Editing a soft-deleted banner (still listed in the admin UI) means
        // the operator wants it back live — restore it so the change sticks.
        if ($banner->trashed()) {
            $banner->restore();
        }

        $banner->update($data);
        $this->bannerService->flushCache();

        return ApiResponse::success(new BannerResource($banner->fresh()), 'Banner updated successfully');
    }

    public function restore(string $uuid): JsonResponse
    {
        $banner = Banner::withTrashed()->where('uuid', $uuid)->firstOrFail();
        $banner->restore();
        $this->bannerService->flushCache();

        return ApiResponse::success(new BannerResource($banner->fresh()), 'Banner restored successfully');
    }

    public function destroy(string $uuid): JsonResponse
    {
        $banner = Banner::where('uuid', $uuid)->firstOrFail();
        $banner->delete();
        $this->bannerService->flushCache();

        return ApiResponse::success(null, 'Banner deleted successfully');
    }

    private function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'image_key' => ['nullable', 'string', 'max:500'],
            'mobile_image_key' => ['nullable', 'string', 'max:500'],
            'link_type' => ['nullable', 'string', 'in:none,product,category,brand,search,external'],
            'link_value' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'string', 'in:hero,promo,sponsored,top_brands,just_launched,split,single'],
            'placement' => ['required', 'string', 'in:home,category,search,product_detail'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer'],
            'brand_ids' => ['nullable', 'array'],
            'brand_ids.*' => ['integer'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer'],
            'search_keywords' => ['nullable', 'array'],
            'search_keywords.*' => ['string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
