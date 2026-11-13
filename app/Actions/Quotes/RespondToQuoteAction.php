<?php

namespace App\Actions\Quotes;

use App\Enums\QuoteResponseStatus;
use App\Mail\QuoteDecisionMail;
use App\Models\QuoteActivity;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Buyer-side decision on a sent quote revision.
 */
class RespondToQuoteAction
{
    public function accept(QuoteRequest $quote): QuoteRequest
    {
        $response = $this->respondable($quote);

        DB::transaction(function () use ($quote, $response): void {
            $response->markAsAccepted();
            $quote->markAsAccepted();
        });

        QuoteActivity::create([
            'quote_request_id' => $quote->id,
            'user_id' => null,
            'type' => QuoteActivity::ACCEPTED,
            'meta' => [
                'response_uuid' => $response->uuid,
                'revision' => $response->revision,
            ],
        ]);

        $this->notifyTeam($quote, $response, 'accepted');

        return $quote->fresh(['latestResponse']);
    }

    public function decline(QuoteRequest $quote, ?string $reason = null): QuoteRequest
    {
        $response = $this->respondable($quote);

        DB::transaction(function () use ($quote, $response): void {
            $response->markAsDeclined();
            $quote->markAsDeclined();
        });

        QuoteActivity::create([
            'quote_request_id' => $quote->id,
            'user_id' => null,
            'type' => QuoteActivity::DECLINED,
            'meta' => [
                'response_uuid' => $response->uuid,
                'revision' => $response->revision,
                'reason' => $reason,
            ],
        ]);

        $this->notifyTeam($quote, $response, 'declined', $reason);

        return $quote->fresh(['latestResponse']);
    }

    /**
     * Alert the owner (or the fallback mailbox) that the buyer decided.
     * Notification failures must never fail the buyer's request.
     *
     * @param  'accepted'|'declined'  $decision
     */
    private function notifyTeam(
        QuoteRequest $quote,
        QuoteResponse $response,
        string $decision,
        ?string $reason = null,
    ): void {
        $recipient = $quote->assignedTo?->email
            ?: config('quotes.notifications.fallback_email');

        if (blank($recipient)) {
            return;
        }

        try {
            Mail::to($recipient)->queue(new QuoteDecisionMail($quote, $response, $decision, $reason));
        } catch (Throwable $e) {
            Log::warning('Failed to notify the team about a quote decision', [
                'quote_id' => $quote->id,
                'decision' => $decision,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The latest revision the customer is still allowed to answer.
     */
    private function respondable(QuoteRequest $quote): QuoteResponse
    {
        $response = $quote->latestResponse;

        if ($response === null) {
            throw ValidationException::withMessages([
                'response' => 'This quote has not been answered yet.',
            ]);
        }

        if (! in_array($response->status, [QuoteResponseStatus::SENT, QuoteResponseStatus::VIEWED], true)) {
            throw ValidationException::withMessages([
                'response' => 'This quote can no longer be updated.',
            ]);
        }

        if ($response->isExpired()) {
            $quote->markAsExpired();

            throw ValidationException::withMessages([
                'response' => 'This quote offer has expired.',
            ]);
        }

        return $response;
    }
}
