<?php

namespace Tests\Feature\Quote;

use App\Models\QuoteRequest;
use App\Models\User;
use App\Services\Quotes\QuoteService;
use Illuminate\Support\Facades\Mail;

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

beforeEach(function () {
    Mail::fake();
});

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

        $this->getJson('/api/v1/quotes/'.$request->uuid.'/tracking')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.reference_number', 'QR-'.$request->id);
    });

    it('returns 404 for an unknown reference', function () {
        $this->getJson('/api/v1/quotes/does-not-exist/tracking')->assertNotFound();
    });
});

describe('responding to a quote request', function () {
    it('creates a response and marks the request replied', function () {
        $sender = User::factory()->create();
        $request = app(QuoteService::class)->createQuoteRequest(quotePayload());

        $response = app(QuoteService::class)->sendQuoteResponse($request, [
            'subject' => 'Your quote',
            'message' => 'Here is the pricing.',
            'items' => [
                ['name' => 'Drone motor', 'quantity' => 50, 'unit_price' => 500],
            ],
            'currency' => 'PKR',
            'validity_days' => 14,
        ], $sender->id);

        expect($response->status)->toBe('sent')
            ->and($response->sent_at)->not->toBeNull()
            ->and((float) $response->total_amount)->toBe(25000.0);

        $request->refresh();
        expect($request->status)->toBe('replied')
            ->and($request->quoted_at)->not->toBeNull()
            ->and($request->first_response_at)->not->toBeNull();
    });

    it('keeps the request untouched when saving a draft', function () {
        $sender = User::factory()->create();
        $request = app(QuoteService::class)->createQuoteRequest(quotePayload());

        $draft = app(QuoteService::class)->sendQuoteResponse($request, [
            'subject' => 'Draft offer',
            'message' => 'Still working on this.',
            'send' => false,
            'items' => [
                ['name' => 'Drone motor', 'quantity' => 10, 'unit_price' => 500],
            ],
        ], $sender->id);

        expect($draft->isDraft())->toBeTrue();
        expect($request->fresh()->status)->toBe('pending');
    });

    it('tracks a response view', function () {
        $sender = User::factory()->create();
        $request = app(QuoteService::class)->createQuoteRequest(quotePayload());
        $response = app(QuoteService::class)->sendQuoteResponse($request, [
            'subject' => 'Your quote',
            'message' => 'Here is the pricing.',
            'items' => [
                ['name' => 'Drone motor', 'quantity' => 1, 'unit_price' => 500],
            ],
        ], $sender->id);

        expect($response->viewed_at)->toBeNull();

        $response->trackView();

        expect($response->fresh()->viewed_at)->not->toBeNull();
    });

    it('counts statistics', function () {
        $sender = User::factory()->create();
        $request = app(QuoteService::class)->createQuoteRequest(quotePayload());
        app(QuoteService::class)->sendQuoteResponse($request, [
            'subject' => 'Your quote',
            'message' => 'Here is the pricing.',
            'items' => [
                ['name' => 'Drone motor', 'quantity' => 1, 'unit_price' => 500],
            ],
        ], $sender->id);

        $stats = app(QuoteService::class)->getStatistics();

        expect($stats['total'])->toBe(1)
            ->and($stats['replied'])->toBe(1);
    });
});
