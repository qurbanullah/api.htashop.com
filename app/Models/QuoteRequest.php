<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class QuoteRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'organization_id',
        'quote_number',
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
        'industry_id',
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
        'assigned_to',
        'priority',
        'quoted_at',
        'first_response_at',
        'last_response_at',
        'response_due_at',
        'accepted_at',
        'declined_at',
        'cancelled_at',
        'cancelled_reason',
    ];

    protected $casts = [
        'requirements' => 'array',
        'quoted_at' => 'datetime',
        'first_response_at' => 'datetime',
        'last_response_at' => 'datetime',
        'response_due_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (QuoteRequest $request): void {
            if (empty($request->uuid)) {
                $request->uuid = (string) Str::uuid();
            }
        });

        // The number embeds the auto-increment id, so it can only be assigned
        // after the insert completes.
        static::created(function (QuoteRequest $request): void {
            if (! empty($request->quote_number)) {
                return;
            }

            $request->forceFill([
                'quote_number' => sprintf(
                    'Q-%s-%06d',
                    $request->created_at?->format('Y') ?? now()->format('Y'),
                    $request->id,
                ),
            ])->saveQuietly();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(QuoteResponse::class)
            ->orderByDesc('revision')
            ->orderByDesc('created_at');
    }

    public function latestResponse(): HasOne
    {
        return $this->hasOne(QuoteResponse::class)->latestOfMany();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(QuoteActivity::class)->orderByDesc('created_at');
    }

    public function quotable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Structured industry reference.
     *
     * Not named `industry()` because that attribute holds the legacy free-text
     * value still returned by the API and used by spreadsheet exports.
     */
    public function industryModel(): BelongsTo
    {
        return $this->belongsTo(Industry::class, 'industry_id');
    }

    /**
     * Merchants see quotes for their organizations (and tenant-level quotes);
     * admins see everything.
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $organizationIds = $user->activeOrganizationIds();
        $tenantIds = $user->activeTenantIds();

        return $query->where(function (Builder $inner) use ($organizationIds, $tenantIds): void {
            if ($organizationIds !== []) {
                $inner->whereIn('organization_id', $organizationIds);

                if ($tenantIds !== []) {
                    $inner->orWhere(function (Builder $tenant) use ($tenantIds): void {
                        $tenant->whereNull('organization_id')->whereIn('tenant_id', $tenantIds);
                    });
                }

                return;
            }

            if ($tenantIds !== []) {
                $inner->whereNull('organization_id')->whereIn('tenant_id', $tenantIds);

                return;
            }

            $inner->whereRaw('1 = 0');
        });
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', QuoteStatus::PENDING);
    }

    public function scopeReplied(Builder $query): Builder
    {
        return $query->where('status', QuoteStatus::REPLIED);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, QuoteStatus::OPEN, true);
    }

    /**
     * A still-open quote whose first-response SLA date has passed.
     */
    public function isOverdue(): bool
    {
        return $this->response_due_at !== null
            && $this->response_due_at->isPast()
            && $this->isOpen();
    }

    /**
     * When the buyer (or the team on their behalf) closed the quote.
     */
    public function decidedAt(): ?Carbon
    {
        return $this->accepted_at ?? $this->declined_at;
    }

    public function markAsInProgress(): void
    {
        if ($this->status === QuoteStatus::PENDING) {
            $this->update(['status' => QuoteStatus::IN_PROGRESS]);
        }
    }

    public function markAsReplied(): void
    {
        $this->update([
            'status' => QuoteStatus::REPLIED,
            'quoted_at' => $this->quoted_at ?? now(),
            'last_response_at' => now(),
        ]);
    }

    public function markAsQuoted(): void
    {
        $this->markAsReplied();
    }

    public function markAsAccepted(): void
    {
        $this->update([
            'status' => QuoteStatus::ACCEPTED,
            'accepted_at' => now(),
            'response_due_at' => null,
        ]);
    }

    public function markAsDeclined(): void
    {
        $this->update([
            'status' => QuoteStatus::DECLINED,
            'declined_at' => now(),
            'response_due_at' => null,
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update([
            'status' => QuoteStatus::EXPIRED,
            'response_due_at' => null,
        ]);
    }

    public function markAsCancelled(string $reason): void
    {
        $this->update([
            'status' => QuoteStatus::CANCELLED,
            'cancelled_at' => now(),
            'cancelled_reason' => $reason,
            'response_due_at' => null,
        ]);
    }
}
