<?php

use App\Models\QuoteRequest;
use App\Services\Quotes\QuoteService;

function quotePayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Ahmed',
        'last_name' => 'Khan',
        'email' => 'ahmed@example.test',
        'phone' => '+923001234567',
        'organization' => 'AgriTech Labs',
        'job_title' => 'Engineer',
        'country' => 'Pakistan',
        'city' => 'Lahore',
        'postal_code' => '54000',
        'message' => 'Need a quote for fifty drone motors.',
    ], $overrides);
}

describe('submitting a quote request', function () {
    it('creates a quote request', function () {
        $response = $this->postJson('/api/v1/quotes', quotePayload());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reference_number', 'QR-1');

        expect(QuoteRequest::query()->count())->toBe(1);
    });

    it('returns the status for a known request', function () {
        $request = app(QuoteService::class)->createQuoteRequest(quotePayload());

        $this->getJson('/api/v1/quotes/'.$request->uuid)
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.reference_number', 'QR-'.$request->id);
    });
});

describe('responding to a quote request', function () {
    it('creates a response and marks the request quoted', function () {
        $request = app(QuoteService::class)->createQuoteRequest(quotePayload());

        $response = app(QuoteService::class)->sendQuoteResponse($request, [
            'subject' => 'Your quote',
            'message' => 'Here is the pricing.',
            'total_amount' => 25000,
            'currency' => 'PKR',
            'validity_days' => 14,
        ], 1);

        expect($response->status)->toBe('sent')
            ->and($response->sent_at)->not->toBeNull();

        $request->refresh();
        expect($request->status)->toBe('quoted')
            ->and($request->quoted_at)->not->toBeNull();
    });

    it('tracks a response view', function () {
        $request = app(QuoteService::class)->createQuoteRequest(quotePayload());
        $response = app(QuoteService::class)->sendQuoteResponse($request, [
            'subject' => 'Your quote',
            'message' => 'Here is the pricing.',
        ], 1);

        expect($response->viewed_at)->toBeNull();

        $response->trackView();

        expect($response->fresh()->viewed_at)->not->toBeNull();
    });

    it('counts statistics', function () {
        $request = app(QuoteService::class)->createQuoteRequest(quotePayload());
        app(QuoteService::class)->sendQuoteResponse($request, [
            'subject' => 'Your quote',
            'message' => 'Here is the pricing.',
        ], 1);

        $stats = app(QuoteService::class)->getStatistics();

        expect($stats['total'])->toBe(1)
            ->and($stats['quoted'])->toBe(1);
    });
});
