<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A push-notification destination for one signed-in device.
 *
 * `token` is the FCM registration token (Android) or APNs device token (iOS)
 * and is unique across the table — a token belongs to whichever account most
 * recently signed in on that device.
 */
class DeviceToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'token',
        'platform',
        'device_id',
        'app_version',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DeviceToken $deviceToken): void {
            if (empty($deviceToken->uuid)) {
                $deviceToken->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
