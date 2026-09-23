<?php

namespace App\Models;

use App\Enums\ContactMessageStatusEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ContactMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'user_id',
        'name',
        'email',
        'phone',
        'order_uuid',
        'subject',
        'message',
        'admin_response',
        'status',
        'read_at',
        'replied_at',
        'replied_by',
        'metadata',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'replied_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ContactMessage $message): void {
            if (empty($message->uuid)) {
                $message->uuid = (string) Str::uuid();
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
     * The admin who replied to this message
     */
    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    /**
     * Scope for new messages
     */
    public function scopeNew($query)
    {
        return $query->where('status', ContactMessageStatusEnum::NEW->value);
    }

    /**
     * Scope for read messages
     */
    public function scopeRead($query)
    {
        return $query->where('status', ContactMessageStatusEnum::READ->value);
    }

    /**
     * Mark message as read
     */
    public function markAsRead(): void
    {
        $this->update([
            'status' => ContactMessageStatusEnum::READ->value,
            'read_at' => now(),
        ]);
    }

    /**
     * Mark message as replied and store the admin response
     */
    public function markAsReplied(string $response, int $repliedBy): void
    {
        $this->update([
            'status' => ContactMessageStatusEnum::REPLIED->value,
            'admin_response' => $response,
            'replied_at' => now(),
            'replied_by' => $repliedBy,
        ]);
    }

    /**
     * Check if message is new
     */
    public function isNew(): bool
    {
        return $this->status === ContactMessageStatusEnum::NEW->value;
    }

    /**
     * Check if message is read
     */
    public function isRead(): bool
    {
        return $this->status === ContactMessageStatusEnum::READ->value;
    }

    /**
     * Check if message is replied
     */
    public function isReplied(): bool
    {
        return $this->status === ContactMessageStatusEnum::REPLIED->value;
    }

    /**
     * Get status badge color
     */
    public function statusColor(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->status) {
                ContactMessageStatusEnum::NEW->value => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                ContactMessageStatusEnum::READ->value => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300',
                ContactMessageStatusEnum::REPLIED->value => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
            }
        );
    }
}
