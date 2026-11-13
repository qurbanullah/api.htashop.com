<?php

namespace Tests\Feature\Quote;

use App\Mail\QuoteExpiredMail;
use App\Mail\QuoteExpiringMail;
use App\Mail\QuoteResponseMail;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Support\Quotes\QuotePortalLink;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

function portalQuote(array $attributes = []): QuoteRequest
{
    return QuoteRequest::query()->create(array_merge([
        'first_name' => 'Omar',
        'last_name' => 'Farooq',
        'email' => 'omar@example.test',
        'organization' => 'Buyer Co',
        'job_title' => 'Procurement',
        'country' => 'Pakistan',
        'city' => 'Karachi',
        'postal_code' => '74000',
        'message' => 'Please quote.',
        'status' => 'replied',
        'requirements' => ['product_name' => 'Stepper Motor', 'sku' => 'MOT-17'],
    ], $attributes));
}

function portalResponse(QuoteRequest $quote, array $attributes = []): QuoteResponse
{
    $response = QuoteResponse::query()->create(array_merge([
        'quote_request_id' => $quote->id,
        'subject' => 'Your quotation',
        'message' => 'Please review the attached offer.',
        'currency' => 'PKR',
        'validity_days' => 30,
        'revision' => 1,
        'status' => 'sent',
        'sent_at' => now(),
        'expires_at' => now()->addDays(30),
        'subtotal' => 5000,
        'total_amount' => 5000,
        'incoterm' => 'CIF',
        'incoterm_location' => 'Karachi Port',
        'payment_terms' => 'net_30',
        'shipping_method' => 'sea_freight',
        'lead_time_days' => 21,
        'partial_shipment_allowed' => true,
    ], $attributes));

    $response->items()->create([
        'name' => 'Stepper Motor',
        'sku' => 'MOT-17',
        'quantity' => 10,
        'unit_price' => 500,
        'discount_amount' => 0,
        'tax_rate' => 0,
        'line_total' => 5000,
        'sort_order' => 0,
    ]);

    return $response;
}

describe('buyer portal', function () {
    it('serves the quote for a valid signed link', function () {
        $quote = portalQuote();
        portalResponse($quote);

        $url = URL::temporarySignedRoute('quotes.portal', now()->addHour(), ['uuid' => $quote->uuid]);

        $response = $this->getJson($url)->assertOk();

        expect($response->json('data.quote_number'))->toBe($quote->quote_number)
            ->and($response->json('data.can_respond'))->toBeTrue()
            ->and($response->json('data.latest_response.incoterm'))->toBe('CIF')
            ->and($response->json('data.latest_response.payment_terms_label'))->toBe('Net 30 days')
            ->and($response->json('data.latest_response.items.0.name'))->toBe('Stepper Motor')
            ->and($response->json('data.pdf_url'))->toContain('signature=')
            ->and($response->json('data.actions.accept.signature'))->not->toBeEmpty()
            ->and($response->json('data.actions.decline.signature'))->not->toBeEmpty();
    });

    it('rejects an unsigned request', function () {
        $quote = portalQuote();
        portalResponse($quote);

        $this->getJson('/api/v1/quotes/'.$quote->uuid.'/portal')->assertForbidden();
    });

    it('rejects an expired link', function () {
        $quote = portalQuote();
        portalResponse($quote);

        $url = URL::temporarySignedRoute('quotes.portal', now()->subMinute(), ['uuid' => $quote->uuid]);

        $this->getJson($url)->assertForbidden();
    });

    it('stops accepting a decision once the buyer has decided', function () {
        $quote = portalQuote(['status' => 'accepted']);
        portalResponse($quote, ['status' => 'accepted']);

        $url = URL::temporarySignedRoute('quotes.portal', now()->addHour(), ['uuid' => $quote->uuid]);

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.can_respond', false);
    });

    it('downloads the stored PDF through a signed link', function () {
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.4 quote', 200)]);
        Storage::fake('idrivee2');

        $quote = portalQuote();
        $response = portalResponse($quote);

        $url = URL::temporarySignedRoute('quotes.pdf', now()->addMinutes(30), [
            'uuid' => $quote->uuid,
            'response' => $response->uuid,
        ]);

        $this->get($url)->assertRedirect();

        expect($this->get($url)->headers->get('Location'))
            ->toContain('documents/quotes/'.$quote->uuid)
            ->toContain('.pdf');
    });

    it('requires a signed link to accept or decline', function () {
        $quote = portalQuote();
        portalResponse($quote);

        $this->postJson('/api/v1/quotes/'.$quote->uuid.'/accept')->assertForbidden();

        $this->postJson(
            URL::temporarySignedRoute('quotes.accept', now()->addHour(), ['uuid' => $quote->uuid])
        )->assertOk()->assertJsonPath('data.status', 'accepted');
    });

    it('rejects an expired decision link', function () {
        $quote = portalQuote();
        portalResponse($quote);

        $url = URL::temporarySignedRoute('quotes.decline', now()->subMinute(), ['uuid' => $quote->uuid]);

        $this->postJson($url, ['reason' => 'Too late'])->assertForbidden();
    });

    it('builds the same signed link for the email', function () {
        $quote = portalQuote();
        $response = portalResponse($quote);

        $link = QuotePortalLink::for($quote, $response);

        expect($link)->toContain('/quote/'.$quote->uuid)
            ->and($link)->toContain('signature=')
            ->and($link)->toContain('expires=');
    });

    it('embeds the portal link in the buyer emails', function () {
        $quote = portalQuote();
        $response = portalResponse($quote);

        foreach ([QuoteResponseMail::class, QuoteExpiringMail::class, QuoteExpiredMail::class] as $mail) {
            $html = (new $mail($quote, $response))->render();

            expect($html)->toContain('/quote/'.$quote->uuid)
                ->and($html)->toContain('signature=');
        }
    });
});
