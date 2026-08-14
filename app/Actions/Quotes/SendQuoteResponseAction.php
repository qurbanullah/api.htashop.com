<?php

namespace App\Actions\Quotes;

use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Mail\QuoteResponseMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Exception;

class SendQuoteResponseAction
{
    /**
     * Send a quote response to the customer.
     *
     * @param QuoteRequest $quoteRequest
     * @param array $data
     * @param int $sentBy
     * @return QuoteResponse
     * @throws Exception
     */
    public function execute(QuoteRequest $quoteRequest, array $data, int $sentBy): QuoteResponse
    {
        try {
            DB::beginTransaction();

            // Create quote response
            $quoteResponse = QuoteResponse::create([
                'quote_request_id' => $quoteRequest->id,
                'sent_by' => $sentBy,
                'subject' => $data['subject'],
                'message' => $data['message'],
                'pricing' => $data['pricing'] ?? null,
                'total_amount' => $data['total_amount'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'validity_days' => $data['validity_days'] ?? 30,
                'package_id' => $data['package_id'] ?? null,
                'software_id' => $data['software_id'] ?? null,
                'ltype_id' => $data['ltype_id'] ?? null,
                'attachments' => $data['attachments'] ?? null,
            ]);

            // Mark quote request as quoted
            $quoteRequest->markAsQuoted();

            // Send email via queue
            Mail::to($quoteRequest->email)
                ->queue(new QuoteResponseMail($quoteRequest, $quoteResponse));

            // Mark as sent
            $quoteResponse->markAsSent();

            DB::commit();

            return $quoteResponse;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception("Failed to send quote response: " . $e->getMessage());
        }
    }
}
