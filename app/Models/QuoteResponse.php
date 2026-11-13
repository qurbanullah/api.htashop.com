<?php

namespace App\Models;

use App\Enums\QuoteResponseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class QuoteResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'quote_request_id',
        'sent_by',
        'subject',
        'message',
        'terms',
        'incoterm',
        'incoterm_location',
        'payment_terms',
        'warranty_months',
        'shipping_method',
        'country_of_origin',
        'hs_code',
        'lead_time_days',
        'estimated_ship_date',
        'port_of_loading',
        'port_of_discharge',
        'partial_shipment_allowed',
        'shipping_notes',
        'package_count',
        'gross_weight_kg',
        'net_weight_kg',
        'package_dimensions',
        'packaging_notes',
        'pricing',
        'total_amount',
        'subtotal',
        'tax_amount',
        'shipping_amount',
        'discount_amount',
        'currency',
        'validity_days',
        'expires_at',
        'expiry_notified_at',
        'revision',
        'package_id',
        'software_id',
        'ltype_id',
        'attachments',
        'status',
        'internal_note',
        'sent_at',
        'viewed_at',
        'accepted_at',
        'declined_at',
        'superseded_at',
    ];

    protected $casts = [
        'pricing' => 'array',
        'attachments' => 'array',
        'internal_note' => 'boolean',
        'total_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'validity_days' => 'integer',
        'revision' => 'integer',
        'warranty_months' => 'integer',
        'lead_time_days' => 'integer',
        'estimated_ship_date' => 'date',
        'partial_shipment_allowed' => 'boolean',
        'package_count' => 'integer',
        'gross_weight_kg' => 'decimal:3',
        'net_weight_kg' => 'decimal:3',
        'expires_at' => 'datetime',
        'expiry_notified_at' => 'datetime',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (QuoteResponse $response): void {
            if (empty($response->uuid)) {
                $response->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteResponseItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => QuoteResponseStatus::SENT,
            'sent_at' => now(),
            'expires_at' => $this->expires_at ?? now()->addDays(max(1, (int) ($this->validity_days ?: 30))),
        ]);
    }

    public function markAsViewed(): void
    {
        if ($this->viewed_at === null) {
            $this->update([
                'viewed_at' => now(),
                'status' => QuoteResponseStatus::VIEWED,
            ]);
        }
    }

    public function markAsAccepted(): void
    {
        $this->update([
            'status' => QuoteResponseStatus::ACCEPTED,
            'accepted_at' => now(),
        ]);
    }

    public function markAsDeclined(): void
    {
        $this->update([
            'status' => QuoteResponseStatus::DECLINED,
            'declined_at' => now(),
        ]);
    }

    public function markAsSuperseded(): void
    {
        if ($this->status === QuoteResponseStatus::SUPERSEDED) {
            return;
        }

        $this->update([
            'status' => QuoteResponseStatus::SUPERSEDED,
            'superseded_at' => now(),
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isDraft(): bool
    {
        return $this->status === QuoteResponseStatus::DRAFT;
    }

    /**
     * Close a sent revision whose validity window has passed.
     */
    public function markAsExpired(): void
    {
        if ($this->status === QuoteResponseStatus::EXPIRED) {
            return;
        }

        $this->update(['status' => QuoteResponseStatus::EXPIRED]);
    }

    /**
     * A revision the buyer can still act on and that ends within the window.
     */
    public function isExpiringWithin(int $days): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->isFuture()
            && $this->expires_at->lessThanOrEqualTo(now()->addDays($days));
    }

    public function trackView(): void
    {
        $this->markAsViewed();
    }
}
