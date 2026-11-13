<?php

namespace App\Actions\Quotes;

use App\Enums\QuoteStatus;
use App\Models\QuoteActivity;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateQuoteStatusAction
{
    public function execute(QuoteRequest $quote, string $status, ?User $user, ?string $reason = null): QuoteRequest
    {
        if (! in_array($status, QuoteStatus::ALL, true)) {
            throw ValidationException::withMessages(['status' => 'Invalid quote status.']);
        }

        if (! QuoteStatus::canTransition($quote->status, $status)) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'Cannot move a quote from %s to %s.',
                    QuoteStatus::label($quote->status),
                    QuoteStatus::label($status),
                ),
            ]);
        }

        if (in_array($status, [QuoteStatus::CANCELLED, QuoteStatus::DECLINED], true) && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }

        DB::transaction(function () use ($quote, $status, $reason): void {
            match ($status) {
                QuoteStatus::ACCEPTED => $quote->markAsAccepted(),
                QuoteStatus::DECLINED => $quote->markAsDeclined(),
                QuoteStatus::EXPIRED => $quote->markAsExpired(),
                QuoteStatus::CANCELLED => $quote->markAsCancelled((string) $reason),
                QuoteStatus::REPLIED => $quote->markAsReplied(),
                QuoteStatus::IN_PROGRESS => $quote->update([
                    'status' => QuoteStatus::IN_PROGRESS,
                ]),
                default => null,
            };
        });

        QuoteActivity::create([
            'quote_request_id' => $quote->id,
            'user_id' => $user?->id,
            'type' => QuoteActivity::STATUS_CHANGED,
            'meta' => [
                'to' => $status,
                'reason' => $reason,
            ],
        ]);

        return $quote->fresh();
    }
}
