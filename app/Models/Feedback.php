<?php

namespace App\Models;

use App\Enums\FeedbackPriorityEnum;
use App\Enums\FeedbackStatusEnum;
use App\Enums\FeedbackTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Feedback extends Model
{
    protected $table = 'feedbacks';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'user_id',
        'type',
        'name',
        'email',
        'subject',
        'message',
        'status',
        'priority',
        'page_url',
        'additional_info',
        'source',
        'user_agent',
        'ip_address',
        'admin_response',
        'replied_at',
        'replied_by',
    ];

    protected $casts = [
        'additional_info' => 'array',
        'replied_at' => 'datetime',
    ];

    protected $dates = [
        'replied_at',
        'created_at',
        'updated_at',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($feedback) {
            if (empty($feedback->uuid)) {
                $feedback->uuid = Str::uuid();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin who replied to this feedback
     */
    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    /**
     * Get all comments for this feedback (polymorphic relationship)
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->orderBy('created_at', 'asc');
    }

    /**
     * Scope for filtering by status
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by type
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for searching feedback
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($query) use ($search) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
        });
    }

    /**
     * Get status color for UI display
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            FeedbackStatusEnum::NEW->value => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
            FeedbackStatusEnum::READ->value => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300',
            FeedbackStatusEnum::REPLIED->value => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
            FeedbackStatusEnum::CLOSED->value => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
        };
    }

    /**
     * Get priority color for UI display
     */
    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            FeedbackPriorityEnum::LOW->value => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
            FeedbackPriorityEnum::MEDIUM->value => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300',
            FeedbackPriorityEnum::HIGH->value => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
            FeedbackPriorityEnum::CRITICAL->value => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
        };
    }

    /**
     * Get type display name
     */
    public function getTypeDisplayAttribute(): string
    {
        return FeedbackTypeEnum::tryFrom((string) $this->type)?->label() ?? ucfirst((string) $this->type);
    }

    /**
     * Mark feedback as read
     */
    public function markAsRead(): void
    {
        if ($this->status === FeedbackStatusEnum::NEW->value) {
            $this->update(['status' => FeedbackStatusEnum::READ->value]);
        }
    }

    /**
     * Mark feedback as replied
     */
    public function markAsReplied(string $response, int $repliedBy): void
    {
        $this->update([
            'status' => FeedbackStatusEnum::REPLIED->value,
            'admin_response' => $response,
            'replied_at' => now(),
            'replied_by' => $repliedBy,
        ]);
    }
}
