<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GdprConsent extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'consent_token',
        'user_id',
        'categories',
        'policy_version',
        'source',
        'ip_address',
        'user_agent',
        'accepted_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'categories' => 'array',
        'accepted_at' => 'datetime',
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
     * Relationship: User linked to this consent (if authenticated).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: latest consent for a given token/user.
     */
    public function scopeLatestFor($query, string $consentToken, ?int $userId = null)
    {
        $query->where('consent_token', $consentToken);

        if ($userId) {
            $query->orWhere('user_id', $userId);
        }

        return $query->orderBy('accepted_at', 'desc');
    }
}
