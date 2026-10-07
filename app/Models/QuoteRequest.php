<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class QuoteRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'first_name',
        'last_name',
        'email',
        'business_email',
        'phone',
        'organization',
        'department',
        'job_title',
        'company_size',
        'industry',
        'country',
        'state',
        'city',
        'postal_code',
        'application',
        'message',
        'requirements',
        'quotable_type',
        'quotable_id',
        'ip_address',
        'user_agent',
        'source_page',
        'status',
        'quoted_at',
    ];

    protected $casts = [
        'requirements' => 'array',
        'quoted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (QuoteRequest $request): void {
            if (empty($request->uuid)) {
                $request->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function responses(): HasMany
    {
        return $this->hasMany(QuoteResponse::class)->orderByDesc('created_at');
    }

    public function latestResponse(): HasOne
    {
        return $this->hasOne(QuoteResponse::class)->latestOfMany();
    }

    public function quotable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeQuoted($query)
    {
        return $query->where('status', 'quoted');
    }

    public function markAsQuoted(): void
    {
        $this->update([
            'status' => 'quoted',
            'quoted_at' => now(),
        ]);
    }
}
