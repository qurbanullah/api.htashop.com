<?php

namespace App\Http\Controllers\V1\Seo;

use App\Http\Controllers\Controller;
use App\Services\Seo\SitemapService;
use Illuminate\Http\Response;

/**
 * SEO endpoints — robots.txt and XML sitemaps served to crawlers.
 * Wired through the storefront nginx proxy at /robots.txt and /sitemap*.xml.
 */
class SitemapController extends Controller
{
    public function __construct(
        protected SitemapService $sitemapService,
    ) {
    }

    public function robots(): Response
    {
        return response($this->sitemapService->robots())
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    public function index(): Response
    {
        return $this->xml($this->sitemapService->index());
    }

    public function products(): Response
    {
        return $this->xml($this->sitemapService->products());
    }

    public function categories(): Response
    {
        return $this->xml($this->sitemapService->categories());
    }

    public function posts(): Response
    {
        return $this->xml($this->sitemapService->posts());
    }

    public function pages(): Response
    {
        return $this->xml($this->sitemapService->pages());
    }

    /**
     * Serves the IndexNow verification key file at /{key}.txt.
     * Responds 404 for any other key so unknown crawlers get nothing.
     */
    public function indexnowKey(string $indexnowKey): Response
    {
        $configuredKey = trim((string) config('indexnow.key'));

        if ($configuredKey === '' || !hash_equals($configuredKey, $indexnowKey)) {
            abort(404);
        }

        return response($configuredKey . "\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    private function xml(string $content): Response
    {
        return response($content)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=600');
    }
}
