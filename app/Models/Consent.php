<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Consent extends Model
{
        use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'eula_id',
        'consentable_type',
        'consentable_id',
        'accepted_at',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'accepted_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($consent) {
            if (empty($consent->uuid)) {
                $consent->uuid = (string) Str::uuid();
            }

            if (empty($consent->accepted_at)) {
                $consent->accepted_at = now();
            }
        });
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Relationship: User who gave consent
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: EULA that was accepted
     */
    public function eula(): BelongsTo
    {
        return $this->belongsTo(Eula::class);
    }

    /**
     * Polymorphic relationship: What the consent is for
     * (e.g., Download, License, etc.)
     */
    public function consentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope: Get consents for a specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Get consents for a specific EULA
     */
    public function scopeForEula($query, int $eulaId)
    {
        return $query->where('eula_id', $eulaId);
    }

    /**
     * Scope: Get recent consents
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('accepted_at', '>=', now()->subDays($days));
    }
}
