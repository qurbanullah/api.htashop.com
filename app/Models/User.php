<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\Profile\HasProfile;
use App\Actions\Subscription\SubscribeUserAction;
use App\Notifications\ResetPasswordNotification;
use App\Models\Membership;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles, HasProfile, InteractsWithMedia;

    /** Account status constants */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_BANNED = 'banned';
    public const STATUS_PENDING = 'pending';

    protected $guard_name = 'api';

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'password',
        'status',
        'last_login_at',
        'last_login_ip',
        'email_verification_token',
        'email_verification_sent_at',
        'profile_completed',
        'onboarding_completed',
        'onboarding_completed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_sent_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'profile_completed' => 'boolean',
            'onboarding_completed' => 'boolean',
        ];
    }

    // ── Status helpers ──

    public function isActive(): bool { return $this->status === self::STATUS_ACTIVE; }
    public function isSuspended(): bool { return $this->status === self::STATUS_SUSPENDED; }
    public function isBanned(): bool { return $this->status === self::STATUS_BANNED; }
    public function isPending(): bool { return $this->status === self::STATUS_PENDING; }

    public function scopeActive($query) { return $query->where('status', self::STATUS_ACTIVE); }
    public function scopeSuspended($query) { return $query->where('status', self::STATUS_SUSPENDED); }
    public function scopeBanned($query) { return $query->where('status', self::STATUS_BANNED); }

    /**
     * Register media conversions for avatar images
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(50)
            ->height(50)
            ->sharpen(10)
            ->nonQueued();

        $this->addMediaConversion('small')
            ->width(100)
            ->height(100)
            ->sharpen(10)
            ->nonQueued();

        $this->addMediaConversion('medium')
            ->width(200)
            ->height(200)
            ->sharpen(10)
            ->nonQueued();
    }

    /**
     * Get user's avatars (polymorphic one-to-many relationship)
     * Each avatar represents a different type (original, thumb, small, medium, large)
     */
    public function avatars()
    {
        return $this->morphMany(Avatar::class, 'avatareable');
    }

    /**
     * Get avatar URL for specified type
     *
     * @param string $type Avatar type: 'original', 'thumb', 'small', 'medium', 'large'
     * @return string|null S3 path or null if not available
     */
    public function getAvatarUrl(string $type = 'medium'): ?string
    {
        // Ensure avatars are loaded
        if (!$this->relationLoaded('avatars')) {
            $this->load('avatars');
        }

        $avatar = $this->avatars->where('type', $type)->first();
        return $avatar?->path;
    }

    /**
     * Get all avatar URLs for this user
     * Returns S3 keys for each type
     *
     * @return array<string, string|null>
     */
    public function getAvatarUrls(): array
    {
        // Ensure avatars are loaded
        if (!$this->relationLoaded('avatars')) {
            $this->load('avatars');
        }

        $urls = [
            'original' => null,
            'thumb' => null,
            'small' => null,
            'medium' => null,
            'large' => null,
        ];

        foreach ($this->avatars as $avatar) {
            $urls[$avatar->type] = $avatar->path;
        }

        return $urls;
    }
    /**
     * Get manuscripts where user is the submitting author
     */
    public function manuscripts(): HasMany
    {
        return $this->hasMany(Manuscript::class, 'submitting_author_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    /**
     * All subscriptions owned by this user — product, post,
     * announcement, etc. (unified Subscribe model).
     */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscribe::class, 'subscribable');
    }

    /**
     * Subscribe this user to a subscription type (e.g. 'post').
     */
    public function subscribeTo(string $type): Subscribe
    {
        return app(SubscribeUserAction::class)->handle($this, $type);
    }

    /**
     * Check whether this user is actively subscribed to a type.
     */
    public function isSubscribedTo(string $type): bool
    {
        return $this->subscriptions()
            ->where('type', $type)
            ->where('is_subscribed', true)
            ->exists();
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'memberships')
            ->withPivot(['role', 'is_primary', 'is_active', 'metadata', 'team_id'])
            ->withTimestamps();
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'memberships')
            ->withPivot(['role', 'is_primary', 'is_active', 'metadata', 'organization_id'])
            ->withTimestamps()
            ->wherePivotNotNull('team_id');
    }

    /**
     * Get all email addresses for this user (polymorphic relationship)
     */
    public function emails()
    {
        return $this->morphMany(Email::class, 'emailable');
    }

    public function primaryEmail()
    {
        return $this->morphOne(Email::class, 'emailable')->where('is_primary', true);
    }

    /**
     * Check if user is admin
     * TODO: Replace with proper role-based permissions system
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || $this->hasRole('super-admin');
    }

    /**
     * Check if user can review manuscripts
     */
    public function canReview(): bool
    {
        return $this->hasRole(['reviewer', 'associate-editor', 'editor-in-chief'])
               && $this->hasAcademicProfile()
               && $this->academicProfile->available_for_review;
    }

    /**
     * Check if user can edit journals
     */
    public function canEdit(): bool
    {
        return $this->hasAnyRole(['editor-in-chief', 'associate-editor', 'managing-editor', 'admin']);
    }

    /**
     * Get user's primary academic role
     */
    public function getPrimaryAcademicRole(): ?string
    {
        $roles = $this->getRoleNames()->toArray();

        $academicRoleOrder = [
            'editor-in-chief',
            'associate-editor',
            'managing-editor',
            'guest-editor',
            'reviewer',
            'author'
        ];

        foreach ($academicRoleOrder as $role) {
            if (in_array($role, $roles)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * Get product reviews written by this user
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'user_id');
    }

    /**
     * Get helpful/not-helpful votes cast by this user
     */
    public function reviewVotes(): HasMany
    {
        return $this->hasMany(ReviewVote::class, 'user_id');
    }

    /**
     * Get reviewer assignments for this user
     */
    public function reviewerAssignments(): HasMany
    {
        return $this->hasMany(Reviewer::class, 'user_id');
    }

    /**
     * Get all assignments where this user is assigned to something (tickets, tasks, etc.)
     */
    public function activeAssignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'assigned_to')->where('is_active', true);
    }

    /**
     * Get all assignments where this user assigned something to others
     */
    public function assignmentsMade(): HasMany
    {
        return $this->hasMany(Assignment::class, 'assigned_by');
    }

    /**
     * Get all publications associated with this user
     */
    public function publications()
    {
        return $this->morphMany(Publication::class, 'publishable');
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        \Illuminate\Support\Facades\Log::info('Custom password reset notification triggered', [
            'user_id' => $this->id,
            'email' => $this->email,
            'token' => substr($token, 0, 10) . '...',
            'token_length' => strlen($token),
            'token_starts_with_hash' => str_starts_with($token, '$2y$'),
            'full_token' => $token, // Log full token temporarily for debugging
        ]);

        $this->notify(new ResetPasswordNotification($token));

        \Illuminate\Support\Facades\Log::info('Notification queued', [
            'user_id' => $this->id,
            'email' => $this->email,
        ]);
    }

    /**
     * Generate email verification token
     */
    public function generateEmailVerificationToken(): string
    {
        $token = \Illuminate\Support\Str::random(64);
        $this->update([
            'email_verification_token' => $token,
            'email_verification_sent_at' => now(),
        ]);
        return $token;
    }

    /**
     * Verify email with token
     */
    public function verifyEmailWithToken(string $token): bool
    {
        \Illuminate\Support\Facades\Log::info('Attempting email verification', [
            'user_id' => $this->id,
            'email' => $this->email,
            'token_provided' => substr($token, 0, 20) . '...',
            'token_stored' => $this->email_verification_token ? substr($this->email_verification_token, 0, 20) . '...' : 'NULL',
            'tokens_match' => $this->email_verification_token === $token,
        ]);

        if ($this->email_verification_token === $token) {
            try {
                // Use forceFill to ensure the update happens
                $this->forceFill([
                    'email_verified_at' => now(),
                    'email_verification_token' => null,
                    'email_verification_sent_at' => null,
                ])->save();

                // Refresh to verify the update
                $this->refresh();

                \Illuminate\Support\Facades\Log::info('Email verification database update', [
                    'user_id' => $this->id,
                    'email_verified_at' => $this->email_verified_at,
                    'token_cleared' => $this->email_verification_token === null,
                ]);

                return true;
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Email verification update failed', [
                    'user_id' => $this->id,
                    'error' => $e->getMessage(),
                ]);
                return false;
            }
        }

        \Illuminate\Support\Facades\Log::warning('Email verification token mismatch', [
            'user_id' => $this->id,
            'email' => $this->email,
        ]);

        return false;
    }

    /**
     * Check if email is verified
     */
    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Check if onboarding is complete
     */
    public function isOnboardingComplete(): bool
    {
        return $this->onboarding_completed === true;
    }

    /**
     * Scope for unverified emails
     */
    public function scopeUnverified($query)
    {
        return $query->whereNull('email_verified_at');
    }

    /**
     * Scope for verified emails
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Scope for incomplete onboarding
     */
    public function scopeIncompleteOnboarding($query)
    {
        return $query->where('onboarding_completed', false);
    }

    /**
     * Scope for complete onboarding
     */
    public function scopeCompleteOnboarding($query)
    {
        return $query->where('onboarding_completed', true);
    }
}
