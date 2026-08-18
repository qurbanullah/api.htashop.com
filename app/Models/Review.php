<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Review extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'reviewable_type',
        'reviewable_id',
        'user_id',
        'tenant_id',
        'rating',
        'title',
        'body',
        'is_recommended',
        'is_verified_purchase',
        'status',
        'helpful_count',
        'not_helpful_count',
        'metadata',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_recommended' => 'boolean',
        'is_verified_purchase' => 'boolean',
        'status' => 'string',
        'helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Review $review): void {
            if (empty($review->uuid)) {
                $review->uuid = (string) Str::uuid();
            }
        });
    }

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ReviewVote::class);
    }
}
