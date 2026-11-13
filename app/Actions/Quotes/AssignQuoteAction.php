<?php

namespace App\Actions\Quotes;

use App\Enums\QuoteStatus;
use App\Models\QuoteActivity;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssignQuoteAction
{
    public function execute(QuoteRequest $quote, ?User $assignee, User $actor): QuoteRequest
    {
        DB::transaction(function () use ($quote, $assignee): void {
            $quote->update(['assigned_to' => $assignee?->id]);

            // Assigning an untouched quote signals that somebody has picked it up.
            if ($assignee !== null && $quote->status === QuoteStatus::PENDING) {
                $quote->update(['status' => QuoteStatus::IN_PROGRESS]);
            }
        });

        QuoteActivity::create([
            'quote_request_id' => $quote->id,
            'user_id' => $actor->id,
            'type' => QuoteActivity::ASSIGNED,
            'meta' => [
                'assigned_to' => $assignee?->id,
                'assigned_to_name' => $assignee?->name,
            ],
        ]);

        return $quote->fresh(['assignedTo']);
    }
}
