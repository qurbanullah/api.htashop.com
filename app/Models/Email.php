<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Email extends Model
{
        use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'is_primary',
        'is_verified',
        'verified_at',
        'verification_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * Get the parent emailable model (User, etc.).
     */
    public function emailable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to get only primary emails.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope to get only verified emails.
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }
}
