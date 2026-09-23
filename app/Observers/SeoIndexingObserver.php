<?php

namespace App\Observers;

use App\Enums\PostStatusEnum;
use App\Enums\PostTypeEnum;
use App\Jobs\Seo\SubmitIndexNowJob;
use App\Models\Post;
use App\Models\Product;
use App\Services\Seo\SitemapService;

/**
 * Notifies IndexNow when a public URL becomes live (or is unpublished and
 * then republished). Google is served by the sitemap instead.
 */
class SeoIndexingObserver
{
    public function created(Product|Post $model): void
    {
        $this->maybeSubmit($model, true);
    }

    public function updated(Product|Post $model): void
    {
        $this->maybeSubmit($model, false);
    }

    private function maybeSubmit(Product|Post $model, bool $isNew): void
    {
        if (!config('indexnow.enabled') || trim((string) config('indexnow.key')) === '') {
            return;
        }

        $url = $model instanceof Product
            ? $this->productUrl($model, $isNew)
            : $this->postUrl($model, $isNew);

        if ($url === null) {
            return;
        }

        SubmitIndexNowJob::dispatch([$url]);

        // Refresh the warmed sitemap cache so the fresh URL is discoverable
        // as soon as the cache TTL allows.
        app(SitemapService::class)->warm();
    }

    private function productUrl(Product $product, bool $isNew): ?string
    {
        $live = $product->status === 'active' && (bool) $product->is_active;

        if (!$live) {
            return null;
        }

        // Only notify on creation or when the row becomes live again.
        if (!$isNew && !$product->wasChanged('status') && !$product->wasChanged('is_active')) {
            return null;
        }

        return config('app.frontend_url') . '/products/'
            . $product->slug . '-' . substr((string) $product->uuid, 0, 8);
    }

    private function postUrl(Post $post, bool $isNew): ?string
    {
        $type = $post->type instanceof PostTypeEnum ? $post->type : null;
        $status = $post->status instanceof PostStatusEnum ? $post->status->value : (string) $post->status;

        $live = $status === PostStatusEnum::PUBLISHED->value
            && (bool) $post->is_published_as_blog
            && $type !== null
            && ! blank($post->slug);

        if (! $live) {
            return null;
        }

        $transitioned = $isNew
            || $post->wasChanged('status')
            || $post->wasChanged('is_published_as_blog')
            || $post->wasChanged('type');

        if (! $transitioned) {
            return null;
        }

        $base = config('app.frontend_url');
        $section = $type->webSection();

        return "{$base}{$section}/{$post->slug}";
    }
}
