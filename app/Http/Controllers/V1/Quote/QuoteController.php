<?php

namespace App\Http\Controllers\V1\Quote;

use App\Actions\Quotes\RespondToQuoteAction;
use App\Enums\Incoterm;
use App\Enums\PaymentTerms;
use App\Enums\QuoteResponseStatus;
use App\Enums\QuoteStatus;
use App\Enums\ShippingMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Quote\SubmitQuoteRequestRequest;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Services\Pdf\PdfService;
use App\Services\Quotes\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class QuoteController extends Controller
{
    public function __construct(
        protected QuoteService $quoteService,
    ) {}

    /**
     * Submit a new quote request.
     *
     * @param  Request  $request
     */
    public function submitQuoteRequest(SubmitQuoteRequestRequest $request): JsonResponse
    {

        try {
            $quoteRequest = $this->quoteService->createQuoteRequest($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Quote request submitted successfully. We will get back to you soon.',
                'data' => [
                    'uuid' => $quoteRequest->uuid,
                    'reference_number' => 'QR-'.$quoteRequest->id,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit quote request. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get a quote request by UUID (for tracking).
     */
    public function getQuoteRequest(string $uuid): JsonResponse
    {
        try {
            $quoteRequest = $this->quoteService->getQuoteRequestByUuid($uuid);

            if (! $quoteRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quote request not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'uuid' => $quoteRequest->uuid,
                    'reference_number' => 'QR-'.$quoteRequest->id,
                    'quote_number' => $quoteRequest->quote_number,
                    'status' => $quoteRequest->status,
                    'created_at' => $quoteRequest->created_at->format('Y-m-d H:i:s'),
                    'quoted_at' => $quoteRequest->quoted_at?->format('Y-m-d H:i:s'),
                    'has_response' => $quoteRequest->responses->isNotEmpty(),
                    'latest_response' => $this->publicResponse($quoteRequest->latestResponse),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve quote request',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Buyer accepts the latest quote revision.
     */
    public function accept(string $uuid, RespondToQuoteAction $action): JsonResponse
    {
        $quote = $this->resolveTrackedQuote($uuid);

        if (! $quote instanceof QuoteRequest) {
            return $quote;
        }

        try {
            $action->accept($quote);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Quote accepted successfully.',
            'data' => [
                'uuid' => $quote->uuid,
                'reference_number' => 'QR-'.$quote->id,
                'status' => $quote->fresh()->status,
            ],
        ]);
    }

    /**
     * Buyer declines the latest quote revision.
     */
    public function decline(Request $request, string $uuid, RespondToQuoteAction $action): JsonResponse
    {
        $quote = $this->resolveTrackedQuote($uuid);

        if (! $quote instanceof QuoteRequest) {
            return $quote;
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $action->decline($quote, $data['reason'] ?? null);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Quote declined successfully.',
            'data' => [
                'uuid' => $quote->uuid,
                'reference_number' => 'QR-'.$quote->id,
                'status' => $quote->fresh()->status,
            ],
        ]);
    }

    private function resolveTrackedQuote(string $uuid): QuoteRequest|JsonResponse
    {
        $quote = QuoteRequest::with('latestResponse.items')->where('uuid', $uuid)->first();

        if ($quote === null) {
            return response()->json([
                'success' => false,
                'message' => 'Quote request not found',
            ], 404);
        }

        return $quote;
    }

    /**
     * Public-safe projection of a quote revision.
     *
     * @return array<string, mixed>|null
     */
    private function publicResponse(?QuoteResponse $response): ?array
    {
        if ($response === null) {
            return null;
        }

        return [
            'uuid' => $response->uuid,
            'revision' => (int) $response->revision,
            'status' => $response->status,
            'status_label' => QuoteResponseStatus::label((string) $response->status),
            'subject' => $response->subject,
            'message' => $response->message,
            'terms' => $response->terms,
            'currency' => $response->currency,
            'subtotal' => $response->subtotal !== null ? (float) $response->subtotal : null,
            'discount_amount' => $response->discount_amount !== null ? (float) $response->discount_amount : null,
            'tax_amount' => $response->tax_amount !== null ? (float) $response->tax_amount : null,
            'shipping_amount' => $response->shipping_amount !== null ? (float) $response->shipping_amount : null,
            'total_amount' => $response->total_amount !== null ? (float) $response->total_amount : null,
            'expires_at' => $response->expires_at?->toISOString(),
            'sent_at' => $response->sent_at?->toISOString(),
            'incoterm' => $response->incoterm,
            'incoterm_label' => Incoterm::labelFor($response->incoterm),
            'incoterm_location' => $response->incoterm_location,
            'payment_terms_label' => PaymentTerms::labelFor($response->payment_terms),
            'warranty_months' => $response->warranty_months,
            'shipping_method_label' => ShippingMethod::labelFor($response->shipping_method),
            'country_of_origin' => $response->country_of_origin,
            'hs_code' => $response->hs_code,
            'lead_time_days' => $response->lead_time_days,
            'estimated_ship_date' => $response->estimated_ship_date?->toDateString(),
            'port_of_loading' => $response->port_of_loading,
            'port_of_discharge' => $response->port_of_discharge,
            'partial_shipment_allowed' => (bool) $response->partial_shipment_allowed,
            'package_count' => $response->package_count,
            'gross_weight_kg' => $response->gross_weight_kg !== null ? (float) $response->gross_weight_kg : null,
            'net_weight_kg' => $response->net_weight_kg !== null ? (float) $response->net_weight_kg : null,
            'package_dimensions' => $response->package_dimensions,
            'items' => $response->relationLoaded('items')
                ? $response->items->map(fn ($item): array => [
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => (float) $item->line_total,
                ])->all()
                : [],
        ];
    }

    /**
     * Buyer portal payload for emails sent with a signed link.
     */
    public function portal(string $uuid): JsonResponse
    {
        $quote = QuoteRequest::with(['latestResponse.items', 'latestResponse.sender'])
            ->where('uuid', $uuid)
            ->first();

        if ($quote === null) {
            return response()->json([
                'success' => false,
                'message' => 'Quote request not found',
            ], 404);
        }

        $response = $quote->latestResponse;
        $canRespond = $response !== null
            && in_array($response->status, [QuoteResponseStatus::SENT, QuoteResponseStatus::VIEWED], true)
            && ! $response->isExpired();

        return response()->json([
            'success' => true,
            'data' => [
                'uuid' => $quote->uuid,
                'reference_number' => 'QR-'.$quote->id,
                'quote_number' => $quote->quote_number,
                'status' => $quote->status,
                'status_label' => QuoteStatus::label((string) $quote->status),
                'customer_name' => trim($quote->first_name.' '.$quote->last_name),
                'organization' => $quote->organization,
                'created_at' => $quote->created_at?->toISOString(),
                'expires_at' => $response?->expires_at?->toISOString(),
                'currency' => $response?->currency,
                'latest_response' => $this->publicResponse($response),
                'can_respond' => $canRespond,
                // One-time action links so a decision cannot be posted from a
                // leaked UUID alone. Minted on portal load, valid for a day.
                'actions' => [
                    'accept' => $canRespond ? $this->actionSignature('quotes.accept', $quote) : null,
                    'decline' => $canRespond ? $this->actionSignature('quotes.decline', $quote) : null,
                ],
                'pdf_url' => $response !== null
                    ? URL::temporarySignedRoute(
                        'quotes.pdf',
                        now()->addMinutes((int) config('quotes.portal.pdf_minutes', 30)),
                        ['uuid' => $quote->uuid, 'response' => $response->uuid],
                    )
                    : null,
            ],
        ]);
    }

    /**
     * Signed query params for a buyer decision route.
     *
     * @return array{expires: int, signature: string}
     */
    private function actionSignature(string $route, QuoteRequest $quote): array
    {
        $url = URL::temporarySignedRoute(
            $route,
            now()->addMinutes((int) config('quotes.portal.action_minutes', 1440)),
            ['uuid' => $quote->uuid],
        );

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return [
            'expires' => (int) ($query['expires'] ?? 0),
            'signature' => (string) ($query['signature'] ?? ''),
        ];
    }

    /**
     * Signed PDF download for the buyer portal.
     */
    public function downloadPdf(
        string $uuid,
        string $response,
        PdfService $pdfService,
    ): JsonResponse|RedirectResponse {
        $quote = QuoteRequest::query()->where('uuid', $uuid)->firstOrFail();
        $revision = $quote->responses()->where('uuid', $response)->firstOrFail();

        $document = $pdfService->findQuoteDocument($quote, $revision);

        if ($document === null) {
            try {
                $document = $pdfService->generateForQuote($quote, $revision);
            } catch (RuntimeException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'The quotation document is not available right now.',
                ], 503);
            }
        }

        $url = rtrim((string) config('app.cdn_url'), '/')
            .'/'.ltrim((string) $document->object_key, '/');

        return redirect()->away($url);
    }

    /**
     * Track quote response view.
     */
    public function trackView(string $responseUuid): JsonResponse
    {
        try {
            $response = QuoteResponse::where('uuid', $responseUuid)->first();

            if (! $response) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quote response not found',
                ], 404);
            }

            $response->trackView();

            return response()->json([
                'success' => true,
                'message' => 'View tracked successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to track view',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
