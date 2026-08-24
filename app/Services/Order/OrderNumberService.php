<?php

namespace App\Services\Order;

use App\Models\Organization;
use App\Models\Sequence;
use App\Models\Tenant;

/**
 * Generates human-readable order numbers.
 *
 * Format: ORD-{ABBR}-{YYMMDD}-{SEQ:6}
 *   ABBR  = organization code (fallback: org slug, then tenant slug)
 *   SEQ   = per-tenant sequence, resets yearly, padded to 6 digits
 *
 * The sequence is incremented atomically (row lock) so concurrent orders
 * never collide. The order's real key remains the UUID; the order number is
 * the merchant-facing reference.
 */
class OrderNumberService
{
    public function generate(Tenant $tenant, ?Organization $organization): string
    {
        $year = (int) now()->format('Y');
        $abbr = $this->abbreviation($tenant, $organization);
        $prefix = "ORD-{$abbr}-" . now()->format('ymd');

        /** @var Sequence|null $sequence */
        $sequence = Sequence::query()
            ->where('sequenceable_type', Tenant::class)
            ->where('sequenceable_id', $tenant->id)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            try {
                $sequence = Sequence::create([
                    'sequenceable_type' => Tenant::class,
                    'sequenceable_id' => $tenant->id,
                    'year' => $year,
                    'prefix' => $prefix,
                    'last_number' => 0,
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                // Concurrent creation: re-fetch the row created by the other
                // request and take its lock before incrementing.
                $sequence = Sequence::query()
                    ->where('sequenceable_type', Tenant::class)
                    ->where('sequenceable_id', $tenant->id)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        }

        $sequence->increment('last_number');

        return $prefix . '-' . str_pad((string) $sequence->last_number, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Clean, uppercase, max-4-char abbreviation from org code → org slug → tenant slug.
     */
    private function abbreviation(Tenant $tenant, ?Organization $organization): string
    {
        $value = $organization?->code
            ?? $organization?->slug
            ?? $tenant->slug
            ?? 'STORE';

        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $value));

        return substr($clean, 0, 4) ?: 'STORE';
    }
}
