<?php

namespace App\Services\Banner;

use App\Helpers\CacheHelper;
use App\Models\Banner;
use Illuminate\Database\Eloquent\Collection;

/**
 * Context-aware banner resolution. Banners are resolved per placement and
 * page context (category / search query) and cached.
 */
class BannerService
{
    public function forContext(string $placement, ?int $categoryId = null, ?string $search = null): Collection
    {
        $cacheKey = "banners:{$placement}:" . ($categoryId ?? 'all') . ':' . substr((string) $search, 0, 60);

        return CacheHelper::remember(
            ['banners'],
            $cacheKey,
            600, // 10 minutes — short enough for campaign edits, long enough for page loads
            fn () => $this->query($placement, $categoryId, $search)->get(),
        );
    }

    /**
     * Invalidate all cached banner resolutions. Called after create/update/delete
     * so storefront changes reflect immediately.
     */
    public function flushCache(): void
    {
        CacheHelper::clearTags(['banners']);
    }

    private function query(string $placement, ?int $categoryId, ?string $search)
    {
        $search = $search ? strtolower(trim((string) $search)) : null;

        return Banner::query()
            ->activeNow()
            ->where('placement', $placement)
            ->when($categoryId, function ($query) use ($categoryId) {
                $query->where(function ($inner) use ($categoryId) {
                    $inner->whereNull('category_ids')
                        ->orWhereJsonContains('category_ids', $categoryId);
                });
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->whereNull('search_keywords')
                        ->orWhere(function ($keywordQuery) use ($search) {
                            $keywordQuery->whereNotNull('search_keywords')
                                ->whereJsonContains('search_keywords', $search);
                        });
                });
            })
            ->orderBy('sort_order')
            ->orderByDesc('updated_at');
    }
}
