<?php

namespace App\Console\Commands;

use App\Services\Seo\SitemapService;
use Illuminate\Console\Command;

/**
 * Regenerates the storefront XML sitemaps (pages, products, posts, categories)
 * and reports the URL count for each. Results are cached in the application
 * cache and served to crawlers by the storefront nginx proxy at /sitemap*.xml.
 *
 * Scheduled to run daily — see routes/console.php. The `sitemap:warm` alias is
 * kept for backwards compatibility with existing runbooks.
 */
class GenerateSitemapsCommand extends Command
{
    protected $signature = 'sitemap:generate';

    /** @var array<int, string> */
    protected $aliases = ['sitemap:warm'];

    protected $description = 'Generate the storefront XML sitemaps (pages, products, posts, categories)';

    public function handle(SitemapService $sitemapService): int
    {
        $counts = $sitemapService->generate();

        foreach ($counts as $type => $count) {
            $this->line(sprintf('  %-11s %d URL(s)', $type, $count));
        }

        $this->info('Sitemaps generated successfully.');

        return self::SUCCESS;
    }
}
