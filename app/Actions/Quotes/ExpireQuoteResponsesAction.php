<?php

namespace App\Actions\Quotes;

use App\Enums\QuoteResponseStatus;
use App\Enums\QuoteStatus;
use App\Mail\QuoteExpiredMail;
use App\Mail\QuoteExpiringMail;
use App\Models\QuoteActivity;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Hourly lifecycle sweep for quote revisions.
 *
 * Two independent passes so a failing mail transport cannot stop the batch
 * from being closed:
 *   1. remind buyers whose offer is about to lapse;
 *   2. expire revisions past their validity and close the request.
 */
class ExpireQuoteResponsesAction
{
    /**
     * @return array{reminders: int, expired: int, requests_expired: int}
     */
    public function execute(bool $dryRun = false): array
    {
        $reminders = $this->sendReminders($dryRun);
        $expiry = $this->expireOverdue($dryRun);

        return [
            'reminders' => $reminders,
            'expired' => $expiry['responses'],
            'requests_expired' => $expiry['requests'],
        ];
    }

    /**
     * Email buyers whose revision ends inside the reminder window (once each).
     */
    private function sendReminders(bool $dryRun): int
    {
        $days = max(1, (int) config('quotes.expiry.reminder_days', 2));
        $count = 0;

        QuoteResponse::query()
            ->whereIn('status', [QuoteResponseStatus::SENT, QuoteResponseStatus::VIEWED])
            ->whereNull('expiry_notified_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays($days))
            ->with('quoteRequest')
            ->chunkById(200, function ($responses) use (&$count, $dryRun): void {
                foreach ($responses as $response) {
                    $quote = $response->quoteRequest;

                    if ($quote === null) {
                        continue;
                    }

                    $count++;

                    if ($dryRun) {
                        continue;
                    }

                    try {
                        Mail::to($quote->email)->queue(new QuoteExpiringMail($quote, $response));
                        $response->forceFill(['expiry_notified_at' => now()])->save();
                    } catch (Throwable $e) {
                        Log::warning('Failed to send quote expiry reminder', [
                            'quote_id' => $quote->id,
                            'response_id' => $response->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $count;
    }

    /**
     * @return array{responses: int, requests: int}
     */
    private function expireOverdue(bool $dryRun): array
    {
        $expiredResponses = 0;
        $expiredRequests = 0;

        QuoteResponse::query()
            ->whereIn('status', [QuoteResponseStatus::SENT, QuoteResponseStatus::VIEWED])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->with('quoteRequest')
            ->chunkById(200, function ($responses) use (&$expiredResponses, &$expiredRequests, $dryRun): void {
                foreach ($responses as $response) {
                    $quote = $response->quoteRequest;

                    if ($quote === null) {
                        continue;
                    }

                    $expiredResponses++;

                    $closesRequest = $this->isLatestRevision($quote, $response) && $quote->isOpen();

                    if ($closesRequest) {
                        $expiredRequests++;
                    }

                    if ($dryRun) {
                        continue;
                    }

                    DB::transaction(function () use ($response, $quote, $closesRequest): void {
                        $response->markAsExpired();

                        QuoteActivity::create([
                            'quote_request_id' => $quote->id,
                            'user_id' => null,
                            'type' => QuoteActivity::EXPIRED,
                            'meta' => [
                                'response_uuid' => $response->uuid,
                                'revision' => $response->revision,
                            ],
                        ]);

                        if ($closesRequest) {
                            $quote->markAsExpired();

                            QuoteActivity::create([
                                'quote_request_id' => $quote->id,
                                'user_id' => null,
                                'type' => QuoteActivity::STATUS_CHANGED,
                                'meta' => [
                                    'to' => QuoteStatus::EXPIRED,
                                    'reason' => 'Revision validity ended.',
                                ],
                            ]);
                        }
                    });

                    try {
                        Mail::to($quote->email)->queue(new QuoteExpiredMail($quote, $response));
                    } catch (Throwable $e) {
                        Log::warning('Failed to send quote expired notice', [
                            'quote_id' => $quote->id,
                            'response_id' => $response->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return ['responses' => $expiredResponses, 'requests' => $expiredRequests];
    }

    private function isLatestRevision(QuoteRequest $quote, QuoteResponse $response): bool
    {
        return (int) $quote->responses()->orderByDesc('revision')->value('id') === (int) $response->id;
    }
}
