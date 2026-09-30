<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RefreshTokenRevokedReasonEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One rotation step of a session.
 *
 * The client holds the opaque token; this row holds its SHA-256, the rotation
 * family it belongs to, and the Passport access token issued alongside it.
 *
 * @property string $token_hash
 * @property string $family_id
 * @property Carbon $expires_at
 * @property Carbon|null $last_used_at
 * @property Carbon|null $revoked_at
 * @property RefreshTokenRevokedReasonEnum|null $revoked_reason
 * @property int|null $replaced_by_id
 */
class RefreshToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'token_hash',
        'family_id',
        'device_id',
        'access_token_id',
        'replaced_by_id',
        'revoked_reason',
        'expires_at',
        'last_used_at',
        'revoked_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'revoked_reason' => RefreshTokenRevokedReasonEnum::class,
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (RefreshToken $refreshToken): void {
            if (empty($refreshToken->uuid)) {
                $refreshToken->uuid = (string) Str::uuid();
            }

            if (empty($refreshToken->family_id)) {
                $refreshToken->family_id = (string) Str::uuid();
            }
        });
    }

    // ── Token material ──

    /**
     * A fresh opaque token. 64 bytes of entropy, rendered as hex.
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(64));
    }

    /**
     * The only form of the token that is ever persisted.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    // ── Relations ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The token that replaced this one when it was rotated. */
    public function successor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }

    // ── State ──

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /** Past the idle window, i.e. the session stopped being used. */
    public function isIdle(): bool
    {
        $idleTtl = (int) config('auth_tokens.refresh_idle_ttl');

        if ($idleTtl <= 0) {
            return false;
        }

        $reference = $this->last_used_at ?? $this->created_at;

        return $reference !== null && $reference->addSeconds($idleTtl)->isPast();
    }

    public function isUsable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired() && ! $this->isIdle();
    }

    /**
     * A successor that was never presented. If a client never received its new
     * token the successor stays unused, which is how a lost response is told
     * apart from a genuine replay.
     */
    public function hasUnusedSuccessor(): bool
    {
        return $this->successor !== null
            && ! $this->successor->isRevoked()
            && ! $this->successor->isExpired();
    }

    // ── Scopes ──

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function scopeForFamily(Builder $query, string $familyId): Builder
    {
        return $query->where('family_id', $familyId);
    }
}
