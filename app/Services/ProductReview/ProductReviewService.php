<?php

namespace App\Services\ProductReview;

use App\Actions\ProductReview\ResolveProductAction;
use App\Actions\ProductReview\ReviewCreateAction;
use App\Actions\ProductReview\ReviewReadAction;
use App\Actions\ProductReview\ReviewSummaryReadAction;
use App\Actions\ProductReview\ReviewVoteAction;
use App\Helpers\CacheHelper;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductReviewService
{
    private const SUMMARY_CACHE_TTL = 900; // 15 minutes

    public function __construct(
        protected ResolveProductAction $resolveProductAction,
        protected ReviewReadAction $readAction,
        protected ReviewSummaryReadAction $summaryAction,
        protected ReviewCreateAction $createAction,
        protected ReviewVoteAction $voteAction,
    ) {
    }

    public function list(string $key, int $perPage = 10): LengthAwarePaginator
    {
        $product = $this->resolveProductAction->handle($key);

        return $this->readAction->handle(Product::class, $product->id, $perPage);
    }

    public function summary(string $key): array
    {
        $product = $this->resolveProductAction->handle($key);
        $cacheKey = $this->summaryCacheKey($product->id);

        return CacheHelper::remember(
            ['catalog', 'reviews'],
            $cacheKey,
            self::SUMMARY_CACHE_TTL,
            fn () => $this->summaryAction->handle(Product::class, $product->id),
        );
    }

    public function create(string $key, array $data, ?User $user): Review
    {
        $product = $this->resolveProductAction->handle($key);
        $review = $this->createAction->handle(Product::class, $product->id, $product->tenant_id, $data, $user);

        CacheHelper::forget(['catalog', 'reviews'], $this->summaryCacheKey($product->id));

        return $review;
    }

    public function vote(string $uuid, User $user, bool $helpful): array
    {
        return $this->voteAction->handle($uuid, $user, $helpful);
    }

    private function summaryCacheKey(int $productId): string
    {
        return 'review:summary:' . Product::class . ':' . $productId;
    }
}
