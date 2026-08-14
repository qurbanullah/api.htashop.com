<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ContactMessage extends Model
{
        use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'read_at',
        'metadata',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Scope for new messages
     */
    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    /**
     * Scope for read messages
     */
    public function scopeRead($query)
    {
        return $query->where('status', 'read');
    }

    /**
     * Mark message as read
     */
    public function markAsRead()
    {
        $this->update([
            'status' => 'read',
            'read_at' => now(),
        ]);
    }

    /**
     * Mark message as replied
     */
    public function markAsReplied()
    {
        $this->update([
            'status' => 'replied',
        ]);
    }

    /**
     * Check if message is new
     */
    public function isNew(): bool
    {
        return $this->status === 'new';
    }

    /**
     * Check if message is read
     */
    public function isRead(): bool
    {
        return $this->status === 'read';
    }

    /**
     * Check if message is replied
     */
    public function isReplied(): bool
    {
        return $this->status === 'replied';
    }

    /**
     * Get status badge color
     */
    public function statusColor(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->status) {
                'new' => 'bg-green-100 text-green-800',
                'read' => 'bg-blue-100 text-blue-800',
                'replied' => 'bg-gray-100 text-gray-800',
                default => 'bg-gray-100 text-gray-800',
            }
        );
    }
}
