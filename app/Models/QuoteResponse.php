<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'pricing',
        'total_amount',
        'currency',
        'validity_days',
        'package_id',
        'software_id',
        'ltype_id',
        'attachments',
        'status',
        'sent_at',
        'viewed_at',
    ];

    protected $casts = [
        'pricing' => 'array',
        'attachments' => 'array',
        'total_amount' => 'decimal:2',
        'validity_days' => 'integer',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
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

    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function trackView(): void
    {
        if ($this->viewed_at === null) {
            $this->update(['viewed_at' => now()]);
        }
    }
}
