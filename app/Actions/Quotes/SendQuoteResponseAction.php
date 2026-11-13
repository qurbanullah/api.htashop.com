<?php

namespace App\Actions\Quotes;

use App\Enums\QuoteResponseStatus;
use App\Mail\QuoteResponseMail;
use App\Models\QuoteActivity;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Services\Pdf\PdfService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SendQuoteResponseAction
{
    /**
     * Create a quote response revision, optionally emailing it immediately.
     *
     * Drafts do not touch the request status; sending supersedes the previous
     * revision, marks the request replied and queues the email.
     *
     * @throws Exception
     */
    public function execute(QuoteRequest $quoteRequest, array $data, int $sentBy): QuoteResponse
    {
        $send = (bool) ($data['send'] ?? true);

        try {
            DB::beginTransaction();

            $items = $this->normaliseItems($data['items'] ?? []);
            $totals = $this->totals($items, $data);

            $revision = ((int) $quoteRequest->responses()->max('revision')) + 1;

            $quoteResponse = QuoteResponse::create([
                'quote_request_id' => $quoteRequest->id,
                'sent_by' => $sentBy,
                'subject' => $data['subject'],
                'message' => $data['message'],
                'terms' => $data['terms'] ?? null,
                ...$this->logistics($data),
                'currency' => $data['currency'] ?? 'PKR',
                'validity_days' => $data['validity_days'] ?? 30,
                'revision' => $revision,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount'],
                'tax_amount' => $totals['tax'],
                'shipping_amount' => $totals['shipping'],
                'total_amount' => $totals['total'],
                'attachments' => $data['attachments'] ?? null,
                'status' => QuoteResponseStatus::DRAFT,
                'internal_note' => (bool) ($data['internal_note'] ?? false),
            ]);

            foreach ($items as $index => $item) {
                $quoteResponse->items()->create($item + ['sort_order' => $index]);
            }

            if ($send) {
                $this->dispatch($quoteRequest, $quoteResponse);
            }

            QuoteActivity::create([
                'quote_request_id' => $quoteRequest->id,
                'user_id' => $sentBy,
                'type' => $send ? QuoteActivity::RESPONSE_SENT : QuoteActivity::RESPONSE_DRAFT,
                'meta' => [
                    'response_uuid' => $quoteResponse->uuid,
                    'revision' => $revision,
                    'total_amount' => $totals['total'],
                    'currency' => $quoteResponse->currency,
                ],
            ]);

            DB::commit();

            return $quoteResponse->fresh(['items', 'sender']);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to send quote response: '.$e->getMessage());
        }
    }

    /**
     * Send a revision that was previously saved as a draft.
     */
    public function sendDraft(QuoteRequest $quoteRequest, QuoteResponse $quoteResponse, int $sentBy): QuoteResponse
    {
        if (! $quoteResponse->isDraft()) {
            throw new Exception('Only drafts can be sent.');
        }

        try {
            DB::beginTransaction();

            $this->dispatch($quoteRequest, $quoteResponse);

            QuoteActivity::create([
                'quote_request_id' => $quoteRequest->id,
                'user_id' => $sentBy,
                'type' => QuoteActivity::RESPONSE_SENT,
                'meta' => [
                    'response_uuid' => $quoteResponse->uuid,
                    'revision' => $quoteResponse->revision,
                    'total_amount' => $quoteResponse->total_amount,
                    'currency' => $quoteResponse->currency,
                ],
            ]);

            DB::commit();

            return $quoteResponse->fresh(['items', 'sender']);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to send quote response: '.$e->getMessage());
        }
    }

    /**
     * Supersede older revisions, email the new one and move the request on.
     */
    private function dispatch(QuoteRequest $quoteRequest, QuoteResponse $quoteResponse): void
    {
        $quoteRequest->responses()
            ->where('id', '!=', $quoteResponse->id)
            ->whereNotIn('status', [QuoteResponseStatus::SUPERSEDED, QuoteResponseStatus::DRAFT])
            ->get()
            ->each(fn (QuoteResponse $previous) => $previous->markAsSuperseded());

        $quoteResponse->markAsSent();

        $quoteRequest->update([
            'first_response_at' => $quoteRequest->first_response_at ?? now(),
            'last_response_at' => now(),
            'response_due_at' => null,
        ]);
        $quoteRequest->markAsReplied();

        Mail::to($quoteRequest->email)
            ->queue($this->mailableFor($quoteRequest, $quoteResponse));
    }

    /**
     * Commercial and logistics terms shared by create and draft update.
     *
     * @return array<string, mixed>
     */
    private function logistics(array $data): array
    {
        return [
            'incoterm' => $data['incoterm'] ?? null,
            'incoterm_location' => $data['incoterm_location'] ?? null,
            'payment_terms' => $data['payment_terms'] ?? null,
            'warranty_months' => $data['warranty_months'] ?? null,
            'shipping_method' => $data['shipping_method'] ?? null,
            'country_of_origin' => $data['country_of_origin'] ?? null,
            'hs_code' => $data['hs_code'] ?? null,
            'lead_time_days' => $data['lead_time_days'] ?? null,
            'estimated_ship_date' => $data['estimated_ship_date'] ?? null,
            'port_of_loading' => $data['port_of_loading'] ?? null,
            'port_of_discharge' => $data['port_of_discharge'] ?? null,
            'partial_shipment_allowed' => (bool) ($data['partial_shipment_allowed'] ?? true),
            'shipping_notes' => $data['shipping_notes'] ?? null,
            'package_count' => $data['package_count'] ?? null,
            'gross_weight_kg' => $data['gross_weight_kg'] ?? null,
            'net_weight_kg' => $data['net_weight_kg'] ?? null,
            'package_dimensions' => $data['package_dimensions'] ?? null,
            'packaging_notes' => $data['packaging_notes'] ?? null,
        ];
    }

    /**
     * Build the buyer email, attaching the quotation PDF when enabled. A PDF
     * failure never blocks the reply itself.
     */
    public function mailableFor(QuoteRequest $quoteRequest, QuoteResponse $quoteResponse): QuoteResponseMail
    {
        $mailable = new QuoteResponseMail($quoteRequest, $quoteResponse);

        if (! (bool) config('quotes.pdf.attach_to_email', true)) {
            return $mailable;
        }

        try {
            $document = app(PdfService::class)->generateForQuote($quoteRequest, $quoteResponse);
            $bytes = $document->object_key
                ? Storage::disk('idrivee2')->get($document->object_key)
                : null;

            if (is_string($bytes) && $bytes !== '') {
                $mailable->attachData($bytes, $document->filename, ['mime' => 'application/pdf']);
            }
        } catch (Throwable $e) {
            Log::warning('Quote PDF could not be attached to the response email', [
                'quote_id' => $quoteRequest->id,
                'response_id' => $quoteResponse->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $mailable;
    }

    /**
     * Send a revision that was previously saved as a draft.
     */
    public function updateDraft(QuoteResponse $response, array $data): QuoteResponse
    {
        if (! $response->isDraft()) {
            throw new Exception('Only drafts can be edited.');
        }

        $items = $this->normaliseItems($data['items'] ?? []);
        $totals = $this->totals($items, $data);

        DB::transaction(function () use ($response, $data, $items, $totals): void {
            $response->update([
                'subject' => $data['subject'],
                'message' => $data['message'],
                'terms' => $data['terms'] ?? null,
                ...$this->logistics($data),
                'currency' => $data['currency'] ?? $response->currency,
                'validity_days' => $data['validity_days'] ?? $response->validity_days,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount'],
                'tax_amount' => $totals['tax'],
                'shipping_amount' => $totals['shipping'],
                'total_amount' => $totals['total'],
                'attachments' => $data['attachments'] ?? $response->attachments,
            ]);

            $response->items()->delete();

            foreach ($items as $index => $item) {
                $response->items()->create($item + ['sort_order' => $index]);
            }
        });

        return $response->fresh(['items', 'sender']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function normaliseItems(array $items): array
    {
        return collect($items)->map(function (array $item): array {
            $quantity = (float) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $discount = (float) ($item['discount_amount'] ?? 0);
            $taxRate = (float) ($item['tax_rate'] ?? 0);
            $taxable = max(0, ($quantity * $unitPrice) - $discount);

            return [
                'product_id' => $item['product_id'] ?? null,
                'variant_id' => $item['variant_id'] ?? null,
                'name' => $item['name'],
                'sku' => $item['sku'] ?? null,
                'part_number' => $item['part_number'] ?? null,
                'description' => $item['description'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'tax_rate' => $taxRate,
                'line_total' => round($taxable + ($taxable * $taxRate / 100), 2),
                'lead_time_days' => $item['lead_time_days'] ?? null,
            ];
        })->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal: float, discount: float, tax: float, shipping: float, total: float}
     */
    private function totals(array $items, array $data): array
    {
        $subtotal = 0.0;
        $discount = (float) ($data['discount_amount'] ?? 0);
        $tax = 0.0;

        foreach ($items as $item) {
            $subtotal += (float) $item['quantity'] * (float) $item['unit_price'];
            $discount += (float) $item['discount_amount'];
            $tax += (float) $item['line_total'] - max(
                0,
                ((float) $item['quantity'] * (float) $item['unit_price']) - (float) $item['discount_amount']
            );
        }

        $shipping = (float) ($data['shipping_amount'] ?? 0);

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'tax' => round($tax, 2),
            'shipping' => round($shipping, 2),
            'total' => round($subtotal - $discount + $tax + $shipping, 2),
        ];
    }
}
