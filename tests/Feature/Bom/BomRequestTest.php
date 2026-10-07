<?php

use App\Enums\BomRequestStatus;
use App\Models\BomRequest;
use App\Services\Bom\BomRequestService;
use Illuminate\Validation\ValidationException;

function bomPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ahmed Khan',
        'email' => 'ahmed@example.test',
        'phone' => '+923001234567',
        'company' => 'AgriTech Labs',
        'title' => 'Agricultural drone build',
        'notes' => 'Need a full kit sourced.',
        'lines' => [
            ['part_name' => 'BLDC motor 2212 920KV', 'part_number' => 'TM-2212', 'quantity' => 8, 'unit' => 'pcs'],
            ['part_name' => '30A ESC', 'quantity' => 8, 'unit' => 'pcs', 'source_url' => 'https://example.com/esc'],
        ],
    ], $overrides);
}

describe('submitting a BOM request', function () {
    it('creates the request and its lines', function () {
        $response = $this->postJson('/api/v1/bom-requests', bomPayload());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', BomRequestStatus::PENDING)
            ->assertJsonPath('data.reference_number', 'BOM-1');

        $request = BomRequest::query()->with('lines')->firstOrFail();
        expect($request->lines)->toHaveCount(2)
            ->and($request->lines->first()->part_name)->toBe('BLDC motor 2212 920KV')
            ->and($request->lines->first()->quantity)->toBe(8);
    });

    it('rejects a request with no lines', function () {
        $this->postJson('/api/v1/bom-requests', bomPayload(['lines' => []]))
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    });

    it('rejects a line without a part name', function () {
        $payload = bomPayload();
        $payload['lines'][0]['part_name'] = '';

        $this->postJson('/api/v1/bom-requests', $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('lines.0.part_name');
    });
});

describe('tracking a BOM request', function () {
    it('returns the status for a known reference', function () {
        $request = app(BomRequestService::class)->create(bomPayload());

        $this->getJson('/api/v1/bom-requests/'.$request->uuid)
            ->assertOk()
            ->assertJsonPath('data.status', BomRequestStatus::PENDING)
            ->assertJsonPath('data.reference_number', 'BOM-'.$request->id);
    });

    it('returns 404 for an unknown reference', function () {
        $this->getJson('/api/v1/bom-requests/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404);
    });
});

describe('BomRequestService', function () {
    it('transitions status and rejects an invalid one', function () {
        $request = app(BomRequestService::class)->create(bomPayload());

        $updated = app(BomRequestService::class)->updateStatus($request, BomRequestStatus::QUOTED);
        expect($updated->status)->toBe(BomRequestStatus::QUOTED);

        expect(fn () => app(BomRequestService::class)->updateStatus($request, 'nonsense'))
            ->toThrow(ValidationException::class);
    });

    it('counts requests and lines in statistics', function () {
        app(BomRequestService::class)->create(bomPayload());

        $stats = app(BomRequestService::class)->getStatistics();

        expect($stats['total'])->toBe(1)
            ->and($stats['pending'])->toBe(1)
            ->and($stats['total_lines'])->toBe(2);
    });
});
