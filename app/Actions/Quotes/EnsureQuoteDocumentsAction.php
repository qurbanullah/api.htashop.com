<?php

namespace App\Actions\Quotes;

use App\Enums\QuoteResponseStatus;
use App\Models\QuoteResponse;
use App\Services\Pdf\PdfService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Nightly safety net for quotation documents.
 *
 * Sending a reply attaches the PDF, but generation is allowed to fail so the
 * email is never blocked. This sweep finds recently sent revisions whose
 * document row or stored object is missing and renders them again.
 */
class EnsureQuoteDocumentsAction
{
    public function __construct(
        protected PdfService $pdfService,
    ) {}

    /**
     * @return array{checked: int, generated: int, skipped: int, pending: int, failed: int}
     */
    public function execute(int $days = 30, int $limit = 200, bool $dryRun = false): array
    {
        $since = now()->subDays(max(1, $days));

        $responses = QuoteResponse::query()
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', $since)
            ->whereIn('status', [
                QuoteResponseStatus::SENT,
                QuoteResponseStatus::VIEWED,
                QuoteResponseStatus::ACCEPTED,
                QuoteResponseStatus::DECLINED,
                QuoteResponseStatus::EXPIRED,
                QuoteResponseStatus::SUPERSEDED,
            ])
            ->with('quoteRequest')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        $stats = ['checked' => 0, 'generated' => 0, 'skipped' => 0, 'pending' => 0, 'failed' => 0];

        foreach ($responses as $response) {
            $quote = $response->quoteRequest;

            if ($quote === null) {
                continue;
            }

            $stats['checked']++;

            if (! $this->needsGeneration($response)) {
                $stats['skipped']++;

                continue;
            }

            if ($dryRun) {
                $stats['pending']++;

                continue;
            }

            try {
                $this->pdfService->generateForQuote($quote, $response);
                $stats['generated']++;
            } catch (Throwable $e) {
                $stats['failed']++;

                Log::warning('Could not regenerate a quotation PDF', [
                    'quote_id' => $quote->id,
                    'response_id' => $response->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * A revision needs rendering when it has no document row, or the row points
     * at an object that is no longer in the bucket.
     */
    private function needsGeneration(QuoteResponse $response): bool
    {
        $quote = $response->quoteRequest;

        if ($quote === null) {
            return false;
        }

        $document = $this->pdfService->findQuoteDocument($quote, $response);

        if ($document === null) {
            return true;
        }

        return $document->object_key === null
            || ! Storage::disk('idrivee2')->exists($document->object_key);
    }
}
