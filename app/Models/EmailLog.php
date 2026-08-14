<?php

declare(strict_types=1);

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Email Log Model
 *
 * Tracks all email sending attempts with delivery status and error tracking
 */
class EmailLog extends Model
{
        use HasFactory;

    protected $fillable = [
        'uuid',
        'mailable_type',
        'recipient_email',
        'recipient_name',
        'subject',
        'status',
        'error_message',
        'error_details',
        'context_type',
        'context_id',
        'context_data',
        'user_id',
        'triggered_by_user_id',
        'metadata',
        'sent_at',
        'failed_at',
        'retry_count',
    ];

    protected $casts = [
        'error_details' => 'array',
        'context_data' => 'array',
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the user who received the email
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who triggered the email
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    /**
     * Scope for sent emails
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope for failed emails
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for pending emails
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope by context
     */
    public function scopeByContext($query, string $type, int $id)
    {
        return $query->where('context_type', $type)
            ->where('context_id', $id);
    }

    /**
     * Scope by mailable type
     */
    public function scopeByMailableType($query, string $type)
    {
        return $query->where('mailable_type', $type);
    }

    /**
     * Mark as sent
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage, array $errorDetails = []): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => $errorMessage,
            'error_details' => $errorDetails,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Check if the email was sent successfully
     */
    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    /**
     * Check if the email failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the email is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Get status badge color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'sent' => 'green',
            'failed' => 'red',
            'bounced' => 'orange',
            'pending' => 'yellow',
            default => 'gray',
        };
    }
}
