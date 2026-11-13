<?php

namespace Tests\Feature\Quote;

use App\Models\Document;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function docQuote(): QuoteRequest
{
    return QuoteRequest::query()->create([
        'first_name' => 'Bilal',
        'last_name' => 'Ahmed',
        'email' => 'bilal@example.test',
        'organization' => 'Buyer Co',
        'job_title' => 'Engineer',
        'country' => 'Pakistan',
        'city' => 'Lahore',
        'postal_code' => '54000',
        'message' => 'Please quote.',
        'status' => 'replied',
        'requirements' => ['product_name' => 'Sensor'],
    ]);
}

function docResponse(QuoteRequest $quote, array $attributes = []): QuoteResponse
{
    $response = QuoteResponse::query()->create(array_merge([
        'quote_request_id' => $quote->id,
        'subject' => 'Your quotation',
        'message' => 'Offer enclosed.',
        'currency' => 'PKR',
        'validity_days' => 30,
        'revision' => 1,
        'status' => 'sent',
        'sent_at' => now()->subDay(),
        'expires_at' => now()->addDays(30),
        'subtotal' => 1000,
        'total_amount' => 1000,
    ], $attributes));

    $response->items()->create([
        'name' => 'Sensor',
        'quantity' => 1,
        'unit_price' => 1000,
        'discount_amount' => 0,
        'tax_rate' => 0,
        'line_total' => 1000,
        'sort_order' => 0,
    ]);

    return $response;
}

beforeEach(function () {
    Storage::fake('idrivee2');
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.4 quote', 200)]);
});

describe('quotation document safety net', function () {
    it('generates a document for a sent revision without one', function () {
        $quote = docQuote();
        docResponse($quote);

        $this->artisan('quotes:ensure-documents')->assertSuccessful();

        $document = Document::query()->where('type', 'quotation')->firstOrFail();

        expect($document->documentable_id)->toBe($quote->id);
        Storage::disk('idrivee2')->assertExists($document->object_key);
        Http::assertSentCount(1);
    });

    it('skips revisions that already have a stored document', function () {
        $quote = docQuote();
        docResponse($quote);

        $this->artisan('quotes:ensure-documents')->assertSuccessful();
        $this->artisan('quotes:ensure-documents')->assertSuccessful();

        expect(Document::query()->where('type', 'quotation')->count())->toBe(1);
        Http::assertSentCount(1);
    });

    it('regenerates when the storage object has disappeared', function () {
        $quote = docQuote();
        docResponse($quote);

        $this->artisan('quotes:ensure-documents')->assertSuccessful();

        $document = Document::query()->where('type', 'quotation')->firstOrFail();
        Storage::disk('idrivee2')->delete($document->object_key);

        $this->artisan('quotes:ensure-documents')->assertSuccessful();

        Storage::disk('idrivee2')->assertExists($document->object_key);
        Http::assertSentCount(2);
    });

    it('writes nothing during a dry run', function () {
        $quote = docQuote();
        docResponse($quote);

        $this->artisan('quotes:ensure-documents', ['--dry-run' => true])->assertSuccessful();

        expect(Document::query()->count())->toBe(0);
        Http::assertNothingSent();
    });

    it('ignores revisions outside the lookback window', function () {
        $quote = docQuote();
        docResponse($quote, ['sent_at' => now()->subDays(90)]);

        $this->artisan('quotes:ensure-documents', ['--days' => 30])->assertSuccessful();

        expect(Document::query()->count())->toBe(0);
    });
});
