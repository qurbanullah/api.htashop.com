<?php

namespace App\Jobs\Seo;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Submits a list of URLs to IndexNow (Bing, DuckDuckGo, Yandex, …).
 *
 * Feature-flagged via INDEXNOW_ENABLED so it is a no-op in local/test
 * environments and only starts talking to the network when enabled.
 */
class SubmitIndexNowJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 20;

    /**
     * @param  string[]  $urls  Absolute URLs to notify.
     */
    public function __construct(
        public array $urls,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        if (!config('indexnow.enabled')) {
            return;
        }

        $urls = array_values(array_filter(array_unique($this->urls)));
        $key = trim((string) config('indexnow.key'));

        if ($urls === [] || $key === '') {
            return;
        }

        $host = trim((string) config('indexnow.host'));
        if ($host === '') {
            $host = (string) parse_url(config('app.frontend_url'), PHP_URL_HOST);
        }

        $endpoint = (string) config('indexnow.endpoint');

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->post($endpoint, [
                    'host' => $host,
                    'key' => $key,
                    'keyLocation' => config('app.frontend_url') . '/' . $key . '.txt',
                    'urlList' => array_slice($urls, 0, 10000),
                ]);

            if (!$response->successful()) {
                Log::warning('IndexNow submission failed', [
                    'host' => $host,
                    'status' => $response->status(),
                    'body' => substr((string) $response->body(), 0, 500),
                ]);

                return;
            }

            Log::info('IndexNow submission accepted', [
                'host' => $host,
                'url_count' => count($urls),
            ]);
        } catch (\Throwable $e) {
            Log::warning('IndexNow submission error', [
                'host' => $host,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
