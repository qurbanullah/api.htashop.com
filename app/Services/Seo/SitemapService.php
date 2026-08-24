<?php

namespace App\Services\Seo;

use App\Helpers\CacheHelper;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Carbon;

/**
 * Generates robots.txt and XML sitemaps for the storefront.
 * Results are cached (10 min) so crawler traffic never hammers the database.
 */
class SitemapService
{
    private const CACHE_TTL = 600; // 10 minutes

    public function storefrontUrl(): string
    {
        return rtrim((string) config('app.storefront_url', 'https://www.htashop.com'), '/');
    }

    public function robots(): string
    {
        $content = "User-agent: *\nAllow: /\n\n";
        $content .= 'Sitemap: ' . $this->storefrontUrl() . "/sitemap.xml\n";

        return $content;
    }

    public function index(): string
    {
        $base = $this->storefrontUrl();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $xml .= $this->sitemapEntry($base . '/sitemap-products.xml');
        $xml .= $this->sitemapEntry($base . '/sitemap-categories.xml');
        $xml .= $this->sitemapEntry($base . '/sitemap-pages.xml');
        $xml .= '</sitemapindex>' . "\n";

        return $xml;
    }

    public function products(): string
    {
        return CacheHelper::remember(['sitemap'], 'sitemap:products', self::CACHE_TTL, function () {
            $base = $this->storefrontUrl();

            $products = Product::query()
                ->where('status', 'active')
                ->where('is_active', true)
                ->orderByDesc('updated_at')
                ->limit(50000)
                ->get(['slug', 'uuid', 'updated_at']);

            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

            foreach ($products as $product) {
                $loc = $base . '/products/' . $product->slug . '-' . substr((string) $product->uuid, 0, 8);
                $xml .= $this->urlEntry(
                    $loc,
                    $this->lastmod($product->updated_at),
                    'daily',
                    '0.8',
                );
            }

            $xml .= '</urlset>' . "\n";

            return $xml;
        });
    }

    public function categories(): string
    {
        return CacheHelper::remember(['sitemap'], 'sitemap:categories', self::CACHE_TTL, function () {
            $base = $this->storefrontUrl();

            $categories = Category::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'updated_at']);

            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

            foreach ($categories as $category) {
                $xml .= $this->urlEntry(
                    $base . '/products?category=' . $category->id,
                    $this->lastmod($category->updated_at),
                    'weekly',
                    '0.6',
                );
            }

            $xml .= '</urlset>' . "\n";

            return $xml;
        });
    }

    public function pages(): string
    {
        $base = $this->storefrontUrl();

        $pages = [
            ['/products', 'weekly', '0.7'],
            ['/policies/privacy-policy', 'monthly', '0.3'],
            ['/policies/terms-of-use', 'monthly', '0.3'],
            ['/policies/cookies-policy', 'monthly', '0.3'],
            ['/policies/refund-policy', 'monthly', '0.3'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $xml .= $this->urlEntry($base . '/', 'weekly', '1.0');

        foreach ($pages as [$path, $freq, $priority]) {
            $xml .= $this->urlEntry($base . $path, null, $freq, $priority);
        }

        $xml .= '</urlset>' . "\n";

        return $xml;
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
