<?php

namespace App\Services\Seo;

use App\Enums\PostStatusEnum;
use App\Enums\PostTypeEnum;
use App\Helpers\CacheHelper;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Support\Carbon;

/**
 * Generates robots.txt and XML sitemaps for the storefront.
 * Results are cached so crawler traffic never hammers the database; the
 * `sitemap:generate` artisan command refreshes them on a schedule.
 */
class SitemapService
{
    private const CACHE_TTL = 600; // 10 minutes

    /**
     * Single source of truth for the storefront origin — driven by the
     * FRONTEND_URL environment variable (config('app.frontend_url')).
     */
    public function storefrontUrl(): string
    {
        return rtrim((string) config('app.frontend_url', config('app.storefront_url', 'https://htashop.com')), '/');
    }

    public function robots(): string
    {
        $content = "User-agent: *\nAllow: /\n";
        $content .= "Disallow: /account\n";
        $content .= "Disallow: /checkout\n";
        $content .= "Disallow: /orders\n";
        $content .= "Disallow: /unsubscribe\n\n";
        $content .= 'Sitemap: ' . $this->storefrontUrl() . "/sitemap.xml\n";

        return $content;
    }

    public function index(): string
    {
        $base = $this->storefrontUrl();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $xml .= $this->sitemapEntry($base . '/sitemap-products.xml');
        $xml .= $this->sitemapEntry($base . '/sitemap-posts.xml');
        $xml .= $this->sitemapEntry($base . '/sitemap-categories.xml');
        $xml .= $this->sitemapEntry($base . '/sitemap-pages.xml');
        $xml .= '</sitemapindex>' . "\n";

        return $xml;
    }

    public function products(): string
    {
        return $this->remember('sitemap:products', function () {
            $base = $this->storefrontUrl();

            $products = Product::query()
                ->where('status', 'active')
                ->where('is_active', true)
                ->orderByDesc('updated_at')
                ->limit(50000)
                ->get(['slug', 'uuid', 'updated_at']);

            $xml = $this->openUrlset();

            foreach ($products as $product) {
                $loc = $base . '/products/' . $product->slug . '-' . substr((string) $product->uuid, 0, 8);
                $xml .= $this->urlEntry($loc, $this->lastmod($product->updated_at), 'daily', '0.8');
            }

            return $xml . $this->closeUrlset();
        });
    }

    /**
     * Posts published as browsable web pages — blog, news, events and every
     * other type flagged `is_published_as_blog` (announcements, promotions, …).
     * The section comes from PostTypeEnum::webSection() so it can never drift
     * from the URLs the API and the IndexNow observer advertise.
     */
    public function posts(): string
    {
        return $this->remember('sitemap:posts', function () {
            $base = $this->storefrontUrl();

            $posts = Post::query()
                ->where('status', PostStatusEnum::PUBLISHED->value)
                ->where('is_published_as_blog', true)
                ->orderByDesc('updated_at')
                ->get(['id', 'type', 'slug', 'updated_at']);

            $xml = $this->openUrlset();

            foreach ($posts as $post) {
                if (! $post->type instanceof PostTypeEnum || blank($post->slug)) {
                    continue;
                }

                $section = $post->type->webSection();

                $xml .= $this->urlEntry(
                    "{$base}{$section}/{$post->slug}",
                    $this->lastmod($post->updated_at),
                    'weekly',
                    '0.7',
                );
            }

            return $xml . $this->closeUrlset();
        });
    }

    public function categories(): string
    {
        return $this->remember('sitemap:categories', function () {
            $base = $this->storefrontUrl();

            $categories = Category::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'updated_at']);

            $xml = $this->openUrlset();

            foreach ($categories as $category) {
                $xml .= $this->urlEntry(
                    $base . '/products?category=' . $category->id,
                    $this->lastmod($category->updated_at),
                    'weekly',
                    '0.6',
                );
            }

            return $xml . $this->closeUrlset();
        });
    }

    public function pages(): string
    {
        $base = $this->storefrontUrl();

        $pages = [
            // Home
            ['/', 'weekly', '1.0', null],
            // Content lists
            ['/products', 'daily', '0.9', null],
            ['/blogs', 'weekly', '0.7', null],
            ['/news', 'weekly', '0.7', null],
            ['/events', 'weekly', '0.6', null],
            ['/contact', 'monthly', '0.5', null],
            ['/feedback', 'monthly', '0.4', null],
            // Legal / compliance
            ['/policies/privacy-policy', 'monthly', '0.3', null],
            ['/policies/terms-of-use', 'monthly', '0.3', null],
            ['/policies/cookies-policy', 'monthly', '0.3', null],
            ['/policies/refund-policy', 'monthly', '0.3', null],
        ];

        $xml = $this->openUrlset();

        foreach ($pages as [$path, $freq, $priority, $lastmod]) {
            $xml .= $this->urlEntry($base . $path, $lastmod, $freq, $priority);
        }

        return $xml . $this->closeUrlset();
    }

    /**
     * Regenerate every storefront sitemap into the cache.
     *
     * @return array<string, int> URL count per sitemap type.
     */
    public function generate(): array
    {
        $counts = [];

        foreach (['products', 'posts', 'categories', 'pages'] as $type) {
            $xml = match ($type) {
                'products' => $this->products(),
                'posts' => $this->posts(),
                'categories' => $this->categories(),
                default => $this->pages(),
            };

            $counts[$type] = substr_count($xml, '<url>');
        }

        return $counts;
    }

    /**
     * Warm all cached sitemaps so crawler requests never build them lazily.
     */
    public function warm(): void
    {
        $this->generate();
    }

    private function remember(string $key, \Closure $callback): string
    {
        return CacheHelper::remember(['sitemap'], $key, self::CACHE_TTL, $callback);
    }

    private function openUrlset(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    }

    private function closeUrlset(): string
    {
        return '</urlset>' . "\n";
    }

    private function urlEntry(string $loc, ?string $lastmod, string $changefreq, string $priority): string
    {
        $entry = '  <url><loc>' . e($loc) . '</loc>';
        if ($lastmod) {
            $entry .= '<lastmod>' . $lastmod . '</lastmod>';
        }
        $entry .= '<changefreq>' . $changefreq . '</changefreq>';
        $entry .= '<priority>' . $priority . '</priority></url>' . "\n";

        return $entry;
    }

    private function sitemapEntry(string $loc): string
    {
        return '  <sitemap><loc>' . e($loc) . '</loc></sitemap>' . "\n";
    }

    private function lastmod(mixed $date): ?string
    {
        return $date instanceof Carbon ? $date->toDateString() : null;
    }
}
